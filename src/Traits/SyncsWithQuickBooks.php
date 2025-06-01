<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Traits;

use Illuminate\Support\Facades\Auth;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services\QuickBooksService;
use QuickBooksOnline\API\Exception\ServiceException;

trait SyncsWithQuickBooks
{
    /**
     * Sync this model to QuickBooks.
     *
     * @return bool
     * @throws ServiceException
     */
    public function syncToQuickBooks(): bool
    {
        $token = $this->getQuickBooksToken();
        if (!$token) {
            throw new \Exception('No valid QuickBooks token found for user');
        }

        $quickBooksService = app(QuickBooksService::class);
        $dataService = $quickBooksService->getAuthenticatedDataService($token);

        try {
            if ($this->quickbooks_id) {
                // Update existing entity
                return $this->updateInQuickBooks($dataService);
            } else {
                // Create new entity
                return $this->createInQuickBooks($dataService);
            }
        } catch (ServiceException $e) {
            throw $e;
        }
    }

    /**
     * Delete this model from QuickBooks.
     *
     * @return bool
     * @throws ServiceException
     */
    public function deleteFromQuickBooks(): bool
    {
        if (!$this->quickbooks_id) {
            return true; // Nothing to delete in QuickBooks
        }

        $token = $this->getQuickBooksToken();
        if (!$token) {
            throw new \Exception('No valid QuickBooks token found for user');
        }

        $quickBooksService = app(QuickBooksService::class);
        $dataService = $quickBooksService->getAuthenticatedDataService($token);

        try {
            $entity = $this->getQuickBooksEntity($dataService);
            if ($entity) {
                $result = $dataService->Delete($entity);
                if ($result) {
                    $this->update([
                        'quickbooks_id' => null,
                        'sync_token' => null,
                        'last_synced_at' => now(),
                    ]);
                    return true;
                }
            }
            return false;
        } catch (ServiceException $e) {
            throw $e;
        }
    }

    /**
     * Sync all entities of this type from QuickBooks for a user.
     *
     * @param int $userId
     * @return int Number of entities synced
     * @throws ServiceException
     */
    public static function syncAllFromQuickBooks(int $userId): int
    {
        $token = QuickBooksToken::where('user_id', $userId)->active()->valid()->first();
        if (!$token) {
            throw new \Exception('No valid QuickBooks token found for user');
        }

        $quickBooksService = app(QuickBooksService::class);
        $dataService = $quickBooksService->getAuthenticatedDataService($token);

        $entityClass = (new static())->getQuickBooksEntityClass();
        $entityName = class_basename($entityClass);

        try {
            $entities = $dataService->Query("SELECT * FROM {$entityName}");
            $syncedCount = 0;

            if ($entities) {
                foreach ($entities as $entity) {
                    static::syncFromQuickBooksEntity($entity, $userId);
                    $syncedCount++;
                }
            }

            return $syncedCount;
        } catch (ServiceException $e) {
            throw $e;
        }
    }

    /**
     * Create entity in QuickBooks.
     *
     * @param \QuickBooksOnline\API\DataService\DataService $dataService
     * @return bool
     */
    protected function createInQuickBooks($dataService): bool
    {
        $entity = $this->toQuickBooksEntity();
        $result = $dataService->Add($entity);

        if ($result) {
            $this->update([
                'quickbooks_id' => $result->Id,
                'sync_token' => $result->SyncToken,
                'last_synced_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Update entity in QuickBooks.
     *
     * @param \QuickBooksOnline\API\DataService\DataService $dataService
     * @return bool
     */
    protected function updateInQuickBooks($dataService): bool
    {
        $entity = $this->getQuickBooksEntity($dataService);
        if (!$entity) {
            // Entity doesn't exist in QuickBooks, create it
            return $this->createInQuickBooks($dataService);
        }

        // Update entity with local data
        $this->updateQuickBooksEntity($entity);
        $result = $dataService->Update($entity);

        if ($result) {
            $this->update([
                'sync_token' => $result->SyncToken,
                'last_synced_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get QuickBooks entity from API.
     *
     * @param \QuickBooksOnline\API\DataService\DataService $dataService
     * @return mixed|null
     */
    protected function getQuickBooksEntity($dataService)
    {
        if (!$this->quickbooks_id) {
            return null;
        }

        $entityClass = $this->getQuickBooksEntityClass();
        $entityName = class_basename($entityClass);

        try {
            $entities = $dataService->Query("SELECT * FROM {$entityName} WHERE Id = '{$this->quickbooks_id}'");
            return $entities ? $entities[0] : null;
        } catch (ServiceException $e) {
            return null;
        }
    }

    /**
     * Convert this model to a QuickBooks entity.
     *
     * @return mixed
     */
    protected function toQuickBooksEntity()
    {
        $entityClass = $this->getQuickBooksEntityClass();
        $entity = new $entityClass();

        // Map model attributes to QuickBooks entity
        $this->mapToQuickBooksEntity($entity);

        return $entity;
    }

    /**
     * Update QuickBooks entity with model data.
     *
     * @param mixed $entity
     */
    protected function updateQuickBooksEntity($entity): void
    {
        $entity->SyncToken = $this->sync_token;
        $this->mapToQuickBooksEntity($entity);
    }

    /**
     * Map model attributes to QuickBooks entity.
     * This method should be overridden in each model.
     *
     * @param mixed $entity
     */
    protected function mapToQuickBooksEntity($entity): void
    {
        // Default implementation - override in each model
        // Example for Customer:
        // $entity->Name = $this->name;
        // $entity->CompanyName = $this->company_name;
    }

    /**
     * Sync from QuickBooks entity to local model.
     *
     * @param mixed $entity
     * @param int $userId
     * @return static
     */
    protected static function syncFromQuickBooksEntity($entity, int $userId)
    {
        $model = static::where('user_id', $userId)
                      ->where('quickbooks_id', $entity->Id)
                      ->first();

        if (!$model) {
            $model = new static(['user_id' => $userId]);
        }

        $model->quickbooks_id = $entity->Id;
        $model->sync_token = $entity->SyncToken;
        $model->last_synced_at = now();

        // Map QuickBooks entity to model attributes
        $model->mapFromQuickBooksEntity($entity);
        $model->save();

        return $model;
    }

    /**
     * Map QuickBooks entity to model attributes.
     * This method should be overridden in each model.
     *
     * @param mixed $entity
     */
    protected function mapFromQuickBooksEntity($entity): void
    {
        // Default implementation - override in each model
        // Example for Customer:
        // $this->name = $entity->Name;
        // $this->company_name = $entity->CompanyName;
    }

    /**
     * Get the QuickBooks entity class name.
     *
     * @return string
     */
    protected function getQuickBooksEntityClass(): string
    {
        return $this->quickbooksClass ?? '';
    }

    /**
     * Get the QuickBooks token for the current user.
     *
     * @return QuickBooksToken|null
     */
    protected function getQuickBooksToken(): ?QuickBooksToken
    {
        $userId = $this->user_id ?? Auth::id();
        
        return QuickBooksToken::where('user_id', $userId)
                             ->active()
                             ->valid()
                             ->first();
    }

    /**
     * Check if this model is synced with QuickBooks.
     *
     * @return bool
     */
    public function isSyncedWithQuickBooks(): bool
    {
        return !is_null($this->quickbooks_id);
    }

    /**
     * Check if this model needs to be synced.
     *
     * @return bool
     */
    public function needsSync(): bool
    {
        if (!$this->isSyncedWithQuickBooks()) {
            return true;
        }

        // Check if local model was updated after last sync
        return $this->updated_at > $this->last_synced_at;
    }

    /**
     * Scope to get models that need syncing.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('quickbooks_id')
              ->orWhere('updated_at', '>', 'last_synced_at');
        });
    }

    /**
     * Scope to get models that are out of sync.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $hours
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutOfSync($query, int $hours = 24)
    {
        return $query->where('last_synced_at', '<', now()->subHours($hours));
    }
}

