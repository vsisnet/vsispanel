<?php

declare(strict_types=1);

namespace App\Modules\Domain\Events;

use App\Modules\Domain\Models\Domain;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DomainCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Domain $domain
    ) {}
}
