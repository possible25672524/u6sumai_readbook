<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Document $document
    ) {}

    public function via(object $notifiable): array
    {
        // Phase 2: database channel only; email can be added later
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'document_processed',
            'document_id' => $this->document->id,
            'title'       => $this->document->title,
            'status'      => $this->document->status,
            'message'     => $this->document->status === Document::STATUS_COMPLETED
                ? "เอกสาร \"{$this->document->title}\" ประมวลผลสำเร็จแล้ว พร้อมใช้งาน"
                : "เอกสาร \"{$this->document->title}\" ประมวลผลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->document->status === Document::STATUS_COMPLETED ? 'สำเร็จ' : 'ไม่สำเร็จ';

        return (new MailMessage)
            ->subject("เอกสาร \"{$this->document->title}\" ประมวลผล{$status}")
            ->line("เอกสาร \"{$this->document->title}\" ได้รับการประมวลผล{$status}แล้ว")
            ->action('ดูเอกสาร', config('frontend.url') . "/documents/{$this->document->id}")
            ->line('ขอบคุณที่ใช้บริการ AI Study Assistant');
    }
}
