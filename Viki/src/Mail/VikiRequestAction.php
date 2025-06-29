<?php

namespace viki\Service\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VikiRequestAction extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * VikiRequestAction constructor.
     * @param $data
     */
    public function __construct($data)
    {	
        $this->data = $data;
    }
	
	
    /**
     * Build the message.
     *
     * @return $this
     */
     public function build()
    {
        return $this->subject('Бюджет е надвишен! Моля предприемете действие')
                    ->view('service::emails.request',['data' => $this->data]);
    }
}
