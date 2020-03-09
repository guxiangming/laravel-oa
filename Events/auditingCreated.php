<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\hlyun_oa_process_flow_auditing;

class auditingCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $auditing;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(hlyun_oa_process_flow_auditing $hlyun_oa_process_flow_auditing)
    {
        //
        $this->auditing=$hlyun_oa_process_flow_auditing;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
