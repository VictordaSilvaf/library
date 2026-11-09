<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Application\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;

class CancelOrderAction extends Action
{
    protected function action(): Response
    {
        return $this->respondWithData(null);
    }
}
