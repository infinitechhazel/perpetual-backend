<?php

namespace App\Observers;

use App\Models\News;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsObserver
{
    /**
     * Handle the News "created" event.
     */
    public function created(News $news): void
    {
        // Only send emails for published news
        if ($news->status === 'published') {
            $this->triggerEmailNotification($news);
        }
    }

    /**
     * Handle the News "updated" event.
     */
    public function updated(News $news): void
    {
        // If news was just published, send notifications
        if ($news->status === 'published' && $news->wasChanged('status')) {
            $this->triggerEmailNotification($news);
        }
    }

    /**
     * Trigger email notification via Next.js API
     */
    private function triggerEmailNotification(News $news): void
    {
        try {
            $nextjsUrl = env('NEXTJS_URL', 'http://localhost:3000');
            
            $response = Http::timeout(60)->post(
                $nextjsUrl . '/api/trigger-news-email',
                [
                    'news' => [
                        'id' => $news->id,
                        'title' => $news->title,
                        'content' => $news->content,
                        'category' => $news->category,
                        'published_at' => $news->published_at,
                        'image' => $news->image,
                    ],
                ]
            );

            if ($response->successful()) {
                $result = $response->json();
                Log::info('News email notification triggered', [
                    'news_id' => $news->id,
                    'results' => $result['results'] ?? null,
                ]);
            } else {
                Log::error('Failed to trigger news email notification', [
                    'news_id' => $news->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error triggering news email notification', [
                'news_id' => $news->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}