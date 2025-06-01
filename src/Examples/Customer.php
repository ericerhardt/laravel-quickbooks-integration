<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Examples;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Traits\SyncsWithQuickBooks;
use QuickBooksOnline\API\Data\IPPCustomer;
use QuickBooksOnline\API\Data\IPPPhysicalAddress;

/**
 * Example Customer Model
 * 
 * This is an example implementation showing how to create a QuickBooks-synced model.
 * Use this as a reference when implementing your own QuickBooks entity models.
 */
class Customer extends Model
{
    use SyncsWithQuickBooks;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quickbooks_customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quickbooks_id',
        'sync_token',
        'name',
        'company_name',
        'email',
        'phone',
        'billing_address_line1',
        'billing_address_line2',
        'billing_address_city',
        'billing_address_state',
        'billing_address_postal_code',
        'billing_address_country',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_address_city',
        'shipping_address_state',
        'shipping_address_postal_code',
        'shipping_address_country',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * QuickBooks entity class name.
     *
     * @var string
     */
    protected $quickbooksClass = IPPCustomer::class;

    /**
     * Get the user that owns this customer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    /**
     * Map model attributes to QuickBooks entity.
     *
     * @param IPPCustomer $entity
     */
    protected function mapToQuickBooksEntity($entity): void
    {
        $entity->Name = $this->name;
        $entity->CompanyName = $this->company_name;
        
        // Primary email
        if ($this->email) {
            $entity->PrimaryEmailAddr = new \QuickBooksOnline\API\Data\IPPEmailAddress();
            $entity->PrimaryEmailAddr->Address = $this->email;
        }

        // Primary phone
        if ($this->phone) {
            $entity->PrimaryPhone = new \QuickBooksOnline\API\Data\IPPTelephoneNumber();
            $entity->PrimaryPhone->FreeFormNumber = $this->phone;
        }

        // Billing address
        if ($this->hasBillingAddress()) {
            $entity->BillAddr = new IPPPhysicalAddress();
            $entity->BillAddr->Line1 = $this->billing_address_line1;
            $entity->BillAddr->Line2 = $this->billing_address_line2;
            $entity->BillAddr->City = $this->billing_address_city;
            $entity->BillAddr->CountrySubDivisionCode = $this->billing_address_state;
            $entity->BillAddr->PostalCode = $this->billing_address_postal_code;
            $entity->BillAddr->Country = $this->billing_address_country;
        }

        // Shipping address
        if ($this->hasShippingAddress()) {
            $entity->ShipAddr = new IPPPhysicalAddress();
            $entity->ShipAddr->Line1 = $this->shipping_address_line1;
            $entity->ShipAddr->Line2 = $this->shipping_address_line2;
            $entity->ShipAddr->City = $this->shipping_address_city;
            $entity->ShipAddr->CountrySubDivisionCode = $this->shipping_address_state;
            $entity->ShipAddr->PostalCode = $this->shipping_address_postal_code;
            $entity->ShipAddr->Country = $this->shipping_address_country;
        }
    }

    /**
     * Map QuickBooks entity to model attributes.
     *
     * @param IPPCustomer $entity
     */
    protected function mapFromQuickBooksEntity($entity): void
    {
        $this->name = $entity->Name;
        $this->company_name = $entity->CompanyName;
        
        // Email
        if ($entity->PrimaryEmailAddr) {
            $this->email = $entity->PrimaryEmailAddr->Address;
        }

        // Phone
        if ($entity->PrimaryPhone) {
            $this->phone = $entity->PrimaryPhone->FreeFormNumber;
        }

        // Billing address
        if ($entity->BillAddr) {
            $this->billing_address_line1 = $entity->BillAddr->Line1;
            $this->billing_address_line2 = $entity->BillAddr->Line2;
            $this->billing_address_city = $entity->BillAddr->City;
            $this->billing_address_state = $entity->BillAddr->CountrySubDivisionCode;
            $this->billing_address_postal_code = $entity->BillAddr->PostalCode;
            $this->billing_address_country = $entity->BillAddr->Country;
        }

        // Shipping address
        if ($entity->ShipAddr) {
            $this->shipping_address_line1 = $entity->ShipAddr->Line1;
            $this->shipping_address_line2 = $entity->ShipAddr->Line2;
            $this->shipping_address_city = $entity->ShipAddr->City;
            $this->shipping_address_state = $entity->ShipAddr->CountrySubDivisionCode;
            $this->shipping_address_postal_code = $entity->ShipAddr->PostalCode;
            $this->shipping_address_country = $entity->ShipAddr->Country;
        }
    }

    /**
     * Check if the customer has a billing address.
     *
     * @return bool
     */
    public function hasBillingAddress(): bool
    {
        return !empty($this->billing_address_line1) || !empty($this->billing_address_city);
    }

    /**
     * Check if the customer has a shipping address.
     *
     * @return bool
     */
    public function hasShippingAddress(): bool
    {
        return !empty($this->shipping_address_line1) || !empty($this->shipping_address_city);
    }

    /**
     * Get the full billing address as a string.
     *
     * @return string
     */
    public function getFullBillingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->billing_address_line1,
            $this->billing_address_line2,
            $this->billing_address_city,
            $this->billing_address_state,
            $this->billing_address_postal_code,
            $this->billing_address_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the full shipping address as a string.
     *
     * @return string
     */
    public function getFullShippingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            $this->shipping_address_city,
            $this->shipping_address_state,
            $this->shipping_address_postal_code,
            $this->shipping_address_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Scope a query to only include customers with email.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email');
    }

    /**
     * Scope a query to only include customers with phone.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPhone($query)
    {
        return $query->whereNotNull('phone');
    }

    /**
     * Scope a query to search customers by name or company.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('company_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }
}

