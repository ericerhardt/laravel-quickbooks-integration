<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Examples;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Traits\SyncsWithQuickBooks;
use QuickBooksOnline\API\Data\IPPInvoice;
use QuickBooksOnline\API\Data\IPPLine;
use QuickBooksOnline\API\Data\IPPSalesItemLineDetail;
use QuickBooksOnline\API\Data\IPPReferenceType;

/**
 * Example Invoice Model
 * 
 * This is an example implementation showing how to create a complex QuickBooks-synced model
 * with line items and customer references.
 */
class Invoice extends Model
{
    use SyncsWithQuickBooks;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quickbooks_invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quickbooks_id',
        'sync_token',
        'customer_id',
        'quickbooks_customer_id',
        'invoice_number',
        'txn_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'balance',
        'status',
        'private_note',
        'customer_memo',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'txn_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    /**
     * QuickBooks entity class name.
     *
     * @var string
     */
    protected $quickbooksClass = IPPInvoice::class;

    /**
     * Get the user that owns this invoice.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    /**
     * Get the customer for this invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the line items for this invoice.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Map model attributes to QuickBooks entity.
     *
     * @param IPPInvoice $entity
     */
    protected function mapToQuickBooksEntity($entity): void
    {
        // Customer reference
        if ($this->quickbooks_customer_id) {
            $entity->CustomerRef = new IPPReferenceType();
            $entity->CustomerRef->value = $this->quickbooks_customer_id;
        }

        // Basic invoice data
        $entity->DocNumber = $this->invoice_number;
        $entity->TxnDate = $this->txn_date?->format('Y-m-d');
        $entity->DueDate = $this->due_date?->format('Y-m-d');
        $entity->PrivateNote = $this->private_note;
        $entity->CustomerMemo = $this->customer_memo;

        // Line items
        $lines = [];
        foreach ($this->lineItems as $index => $lineItem) {
            $line = new IPPLine();
            $line->Id = $index + 1;
            $line->LineNum = $index + 1;
            $line->Amount = $lineItem->amount;
            $line->DetailType = 'SalesItemLineDetail';
            
            $line->SalesItemLineDetail = new IPPSalesItemLineDetail();
            $line->SalesItemLineDetail->Qty = $lineItem->quantity;
            $line->SalesItemLineDetail->UnitPrice = $lineItem->unit_price;
            
            if ($lineItem->quickbooks_item_id) {
                $line->SalesItemLineDetail->ItemRef = new IPPReferenceType();
                $line->SalesItemLineDetail->ItemRef->value = $lineItem->quickbooks_item_id;
            }

            $lines[] = $line;
        }
        
        $entity->Line = $lines;
    }

    /**
     * Map QuickBooks entity to model attributes.
     *
     * @param IPPInvoice $entity
     */
    protected function mapFromQuickBooksEntity($entity): void
    {
        // Customer reference
        if ($entity->CustomerRef) {
            $this->quickbooks_customer_id = $entity->CustomerRef->value;
            
            // Try to find local customer
            $customer = Customer::where('user_id', $this->user_id)
                               ->where('quickbooks_id', $this->quickbooks_customer_id)
                               ->first();
            if ($customer) {
                $this->customer_id = $customer->id;
            }
        }

        // Basic invoice data
        $this->invoice_number = $entity->DocNumber;
        $this->txn_date = $entity->TxnDate ? \Carbon\Carbon::parse($entity->TxnDate) : null;
        $this->due_date = $entity->DueDate ? \Carbon\Carbon::parse($entity->DueDate) : null;
        $this->subtotal = $entity->TotalAmt ?? 0;
        $this->total_amount = $entity->TotalAmt ?? 0;
        $this->balance = $entity->Balance ?? 0;
        $this->private_note = $entity->PrivateNote;
        $this->customer_memo = $entity->CustomerMemo;

        // Status mapping
        $this->status = match($entity->EmailStatus ?? '') {
            'EmailSent' => 'sent',
            'NeedToSend' => 'pending',
            default => 'draft',
        };
    }

    /**
     * Calculate totals after saving line items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->lineItems->sum('amount');
        $this->total_amount = $this->subtotal + $this->tax_amount;
        $this->save();
    }

    /**
     * Check if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->balance <= 0;
    }

    /**
     * Check if the invoice is overdue.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !$this->isPaid();
    }

    /**
     * Scope a query to only include paid invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('balance', '<=', 0);
    }

    /**
     * Scope a query to only include unpaid invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpaid($query)
    {
        return $query->where('balance', '>', 0);
    }

    /**
     * Scope a query to only include overdue invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('balance', '>', 0);
    }

    /**
     * Scope a query to filter by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('txn_date', [$startDate, $endDate]);
    }
}

