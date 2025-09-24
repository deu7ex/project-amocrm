<?php

namespace App\Http\Controllers;

use App\Services\AmoCrmService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class AmoCrmAuthController extends Controller
{
    /**
     * @throws RequestException
     */
    public function callback(Request $request, AmoCrmService $service)
    {
        $service->authByCode($request->get('code'));
        return response('OK, токен сохранён');
    }

}

