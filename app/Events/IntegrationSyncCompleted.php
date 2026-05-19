<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntegrationSyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $companyId,
        public string $provider,
        public int    $orderCount,
        public int    $failed = 0,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("company.{$this->companyId}")];
    }

    public function broadcastAs(): string
    {
        return 'sync.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'provider'    => $this->provider,
            'order_count' => $this->orderCount,
            'failed'      => $this->failed,
            'at'          => now()->toIso8601String(),
        ];
    }
}
