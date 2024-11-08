<?php

namespace App\Transformers\Api\Application;

use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Server;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\NullResource;

class MountTransformer extends BaseTransformer
{
    /**
     * List of resources that can be included.
     */
    protected array $availableIncludes = ['eggs', 'nodes', 'servers'];

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Mount::RESOURCE_NAME;
    }

    public function transform(Mount $model): array
    {
        return $model->toArray();
    }

    /**
     * Return the eggs associated with this mount.
     *
     * @throws \App\Exceptions\Transformer\InvalidTransformerLevelException
     */
    public function includeEggs(Mount $mount): Collection|NullResource
    {
        if (!$this->authorize(Egg::RESOURCE_NAME)) {
            return $this->null();
        }

        $mount->loadMissing('eggs');

        return $this->collection(
            $mount->getRelation('eggs'),
            $this->makeTransformer(EggTransformer::class),
            'egg'
        );
    }

    /**
     * Return the nodes associated with this mount.
     *
     * @throws \App\Exceptions\Transformer\InvalidTransformerLevelException
     */
    public function includeNodes(Mount $mount): Collection|NullResource
    {
        if (!$this->authorize(Node::RESOURCE_NAME)) {
            return $this->null();
        }

        $mount->loadMissing('nodes');

        return $this->collection(
            $mount->getRelation('nodes'),
            $this->makeTransformer(NodeTransformer::class),
            'node'
        );
    }

    /**
     * Return the servers associated with this mount.
     *
     * @throws \App\Exceptions\Transformer\InvalidTransformerLevelException
     */
    public function includeServers(Mount $mount): Collection|NullResource
    {
        if (!$this->authorize(Server::RESOURCE_NAME)) {
            return $this->null();
        }

        $mount->loadMissing('servers');

        return $this->collection(
            $mount->getRelation('servers'),
            $this->makeTransformer(ServerTransformer::class),
            'server'
        );
    }
}
