<?php

namespace App\Services\Servers;

use App\Models\Mount;
use App\Models\Server;

class ServerConfigurationStructureService
{
    /**
     * ServerConfigurationStructureService constructor.
     */
    public function __construct(private EnvironmentService $environment)
    {
    }

    /**
     * Return a configuration array for a specific server when passed a server model.
     *
     * DO NOT MODIFY THIS FUNCTION. This powers legacy code handling for the new daemon
     * daemon, if you modify the structure eggs will break unexpectedly.
     */
    public function handle(Server $server, array $override = []): array
    {
        $clone = $server;
        // If any overrides have been set on this call make sure to update them on the
        // cloned instance so that the configuration generated uses them.
        if (!empty($override)) {
            $clone = $server->fresh();
            foreach ($override as $key => $value) {
                $clone->setAttribute($key, $value);
            }
        }

        return $this->returnFormat($clone);
    }

    /**
     * Returns the data format used for the daemon.
     */
    protected function returnFormat(Server $server): array
    {
        return [
            'uuid' => $server->uuid,
            'meta' => [
                'name' => $server->name,
                'description' => $server->description,
            ],
            'suspended' => $server->isSuspended(),
            'environment' => $this->environment->handle($server),
            'invocation' => $server->startup,
            'skip_egg_scripts' => $server->skip_scripts,
            'build' => [
                'memory_limit' => $server->memory,
                'swap' => $server->swap,
                'io_weight' => $server->io,
                'cpu_limit' => $server->cpu,
                'threads' => $server->threads,
                'disk_space' => $server->disk,
                'oom_killer' => $server->oom_killer,
            ],
            'container' => [
                'image' => $server->image,
                'requires_rebuild' => false,
                'labels' => $server->docker_labels,
            ],
            'allocations' => [
                'force_outgoing_ip' => $server->egg->force_outgoing_ip,
                'default' => [
                    'ip' => $server->allocation->ip,
                    'port' => $server->allocation->port,
                ],
                'mappings' => $server->getAllocationMappings(),
            ],
            'mounts' => $server->mounts->map(function (Mount $mount) {
                return [
                    'source' => $mount->source,
                    'target' => $mount->target,
                    'read_only' => $mount->read_only,
                ];
            }),
            'egg' => [
                'id' => $server->egg->uuid,
                'file_denylist' => $server->egg->inherit_file_denylist,
            ],
        ];
    }
}
