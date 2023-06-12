<?php

namespace Familytree365\Wikitree\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class WikiTreeController extends Controller
{
    private $wikitreeApi;

    public function __construct()
    {
        $this->wikitreeApi = config('services.wikitree.api');
    }

    public function getAuthCode(Request $request)
    {
        $redirectTo = config('services.wikitree.api').'?action=clientLogin&returnURL='.url('api/wikitree/clientLoginResponse');

        return redirect($redirectTo);
    }

    public function getAuthCodeCallBack(Request $request)
    {
        return 'Auth code recieved :: '.$request->authcode;
    }

    public function searchPerson(Request $request)
    {
        $authCode = $request->authcode;
        $client = new \GuzzleHttp\Client();

        $response = $client->request('POST', $this->wikitreeApi, ['query' => [
            'authcode' => $authCode,
            'action' => 'searchPerson',
            'format' => 'json',
            'FirstName' => $request->FirstName,
            'LastName' => $request->LastName,
            'limit' => $request->per_page, // 100
            'start' => ($request->per_page * $request->page) - $request->per_page, // 2,
            'fields' => 'Id, Name, FirstName, MiddleName',
        ]]);

        $statusCode = $response->getStatusCode();
        $content = $response->getBody();
        $persons = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);

        $newPersons = [];
        foreach ($persons[0]['matches'] as $person) {
            $response = $client->request('POST', $this->wikitreeApi, ['query' => [
                'authcode' => $authCode,
                'action' => 'getAncestors',
                'format' => 'json',
                'key' => $person['Id'],
            ]]);

            $statusCode = $response->getStatusCode();
            $content = $response->getBody();
            $ancestors = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);

            $person['ancestors'] = $ancestors;
            array_push($newPersons, $person);
        }

        return response()->json($newPersons);
    }
}
