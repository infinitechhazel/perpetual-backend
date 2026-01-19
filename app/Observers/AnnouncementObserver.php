<?php

namespace App\Observers;

use App\Models\Announcement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnnouncementObserver
{
    public function created(Announcement $announcement): void
    {
        Log::info('AnnouncementObserver: created event triggered', [
            'announcement_id' => $announcement->id,
            'is_active' => $announcement->is_active,
        ]);

        if ($announcement->is_active) {
            $this->triggerEmailNotification($announcement);
        } else {
            Log::info('AnnouncementObserver: Skipping email - announcement not active');
        }
    }

    public function updated(Announcement $announcement): void
    {
        Log::info('AnnouncementObserver: updated event triggered', [
            'announcement_id' => $announcement->id,
            'is_active' => $announcement->is_active,
            'was_changed' => $announcement->wasChanged('is_active'),
        ]);

        if ($announcement->is_active && $announcement->wasChanged('is_active')) {
            $this->triggerEmailNotification($announcement);
        }
    }

    private function triggerEmailNotification(Announcement $announcement): void
    {
        try {
            $nextjsUrl = env('NEXTJS_URL', 'http://localhost:3000');
            
            Log::info('AnnouncementObserver: Attempting to trigger email', [
                'nextjs_url' => $nextjsUrl,
                'announcement_id' => $announcement->id,
            ]);
            
            $response = Http::timeout(60)->post(
                $nextjsUrl . '/api/trigger-announcement-email',
                [
                    'announcement' => [
                        'id' => $announcement->id,
                        'title' => $announcement->title,
                        'description' => $announcement->description,
                        'content' => $announcement->content,
                        'category' => $announcement->category,
                        'date' => $announcement->date,
                    ],
                ]
            );

            if ($response->successful()) {
                $result = $response->json();
                Log::info('AnnouncementObserver: Email notification triggered successfully', [
                    'announcement_id' => $announcement->id,
                    'results' => $result['results'] ?? null,
                ]);
            } else {
                Log::error('AnnouncementObserver: Failed to trigger email notification', [
                    'announcement_id' => $announcement->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('AnnouncementObserver: Exception while triggering email', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}