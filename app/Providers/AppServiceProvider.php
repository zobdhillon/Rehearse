<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);

        Vite::prefetch(concurrency: 3);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
