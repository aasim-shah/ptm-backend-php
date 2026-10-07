<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WebNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $title;
    public $message;
    public $topic;
    public $type;
    public $data;

    public function __construct($title,$message,$topic, $type, $data)
    {

        Log::info('sending pusher notification', ['topic' => $topic, 'title' => $title, 'message' => $message]);
        $this->message = $message;
        $this->title = $title;
        $this->topic = $topic;
        $this->type = $type;
        $this->data = $data;
    }

    public function broadcastAs()
    {
        return $this->topic;
    }
    /**
     * Create a new event instance.
     *
     * @return void
     */

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */

    public function broadcastOn()
    {
        return ['agora-online-channel'];
    }

    public function broadcastWith()
    {
        return array(
            'to' => "/topics/" . $this->topic,
            'priority' => "high",
            'data' => [
                'type' => $this->type,
                'click_action' => "",
                'data' => $this->data
            ],
            'notification' => array("title" => $this->title, "body" => $this->message),
        );
    }
}
