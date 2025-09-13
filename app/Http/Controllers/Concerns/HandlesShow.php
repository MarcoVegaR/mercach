<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\DTO\ShowQuery;
use App\Http\Requests\BaseShowRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trait for handling show operations in controllers.
 *
 * Provides a standardized show method that handles:
 * - Route model binding
 * - Authorization via policies
 * - Request validation and ShowQuery conversion
 * - Service method calling
 * - Inertia response generation
 *
 * @author Laravel Boilerplate
 */
trait HandlesShow
{
    /**
     * Create and validate the show request.
     */
    protected function createShowRequest(Request $request): BaseShowRequest
    {
        $requestClass = $this->showRequestClass();

        /** @var BaseShowRequest $showRequest */
        $showRequest = $requestClass::createFrom($request);
        $showRequest->setContainer(app());
        $showRequest->setRedirector(app('redirect'));
        $showRequest->validateResolved();

        return $showRequest;
    }

    /**
     * Get data from service using the model and query.
     *
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    protected function showUsingService(Model $model, ShowQuery $query): array
    {
        // Determine if model uses UUID or ID
        if (method_exists($model, 'getKey')) {
            $key = $model->getKey();

            // Check if model has UUID attribute and use it
            if ($model->getAttribute('uuid')) {
                return $this->service->showByUuid($model->getAttribute('uuid'), $query);
            }

            // Default to ID
            return $this->service->showById($key, $query);
        }

        throw new \LogicException('Model must have a primary key');
    }

    /**
     * Get the show request class.
     * Override in controller to specify custom request.
     */
    protected function showRequestClass(): string
    {
        return BaseShowRequest::class;
    }

    /**
     * Get the Inertia view name for show.
     * Override in controller to specify view.
     */
    abstract protected function showView(): string;
}
