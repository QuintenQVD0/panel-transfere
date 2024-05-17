<?php

namespace App\Console\Commands\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class QueueWorkerServiceCommand extends Command
{
    protected $description = 'Create the service for the queue worker.';

    protected $signature = 'p:environment:queue-service
        {--service-name= : Name of the queue worker service.}
        {--user= : The user that PHP runs under.}
        {--group= : The group that PHP runs under.}
        {--use-redis : Whether redis is used.}';

    public function handle(): void
    {
        $serviceName = $this->option('service-name') ?? $this->ask('Service name', 'pelican-queue');
        $path = '/etc/systemd/system/' . $serviceName  . '.service';

        if ($this->input->isInteractive()) {
            if (file_exists($path) && !$this->confirm('The service file already exists. Do you want to overwrite it?')) {
                return;
            }

            $user = $this->option('user') ?? $this->ask('User', 'www-data');
            $group = $this->option('group') ?? $this->ask('Group', 'www-data');

            $afterRedis = $this->option('use-redis') ? '\nAfter=redis-server.service' : '';
        } else {
            $user = 'www-data';
            $group = 'www-data';

            $afterRedis = '';
        }

        $basePath = base_path();

        $success = File::put($path, "# Pelican Queue File
# ----------------------------------

[Unit]
Description=Pelican Queue Service$afterRedis

[Service]
User=$user
Group=$group
Restart=always
ExecStart=/usr/bin/php $basePath/artisan queue:work --queue=high,standard,low --tries=3
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
        ");

        if (!$success) {
            $this->error('Error creating service file');

            return;
        }

        $result = Process::run("systemctl enable --now $serviceName.service");
        if ($result->failed()) {
            $this->error('Error enabling service: ' . $result->errorOutput());

            return;
        }

        $this->line('Queue worker service file created successfully.');
    }
}
