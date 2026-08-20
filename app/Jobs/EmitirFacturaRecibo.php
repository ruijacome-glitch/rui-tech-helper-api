<?php
// app/Jobs/EmitirFacturaRecibo.php
namespace App\Jobs;

use App\Models\Pagamento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmitirFacturaRecibo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public Pagamento $pagamento)
    {
    }

    public function handle(): void
    {
    }
}
