<?php

namespace RTippin\Messenger\Repositories;

use Illuminate\Database\Eloquent\Collection;
use RTippin\Messenger\Messenger;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\Thread;

class ImageMessageRepository
{
    /**
     * @var Messenger
     */
    protected Messenger $messenger;

    /**
     * MessageRepository constructor.
     *
     * @param Messenger $messenger
     */
    public function __construct(Messenger $messenger)
    {
        $this->messenger = $messenger;
    }

    /**
     * @param Thread $thread
     * @return Collection
     */
    public function getThreadImagesIndex(Thread $thread)
    {
        return $thread->messages()
            ->image()
            ->latest()
            ->with('owner')
            ->limit($this->messenger->getMessagesIndexCount())
            ->get();
    }

    /**
     * @param Thread $thread
     * @param Message $message
     * @return Collection
     */
    public function getThreadImagesPage(Thread $thread, Message $message)
    {
        return $thread->messages()
            ->image()
            ->latest()
            ->with('owner')
            ->where('created_at', '<=', $message->created_at)
            ->where('id', '!=', $message->id)
            ->limit($this->messenger->getMessagesPageCount())
            ->get();
    }
}