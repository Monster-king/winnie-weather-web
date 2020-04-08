<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Validator;

class WeatherController extends Controller
{
    /**
     * Returns current weather info by lat,lon.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lon' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $errors = $errors->toArray()[$errors->keys()[0]];
            $error = $errors[0];
            throw new HttpException(400, $error);
        }

        $lat = $request->input('lat');
        $lon = $request->input('lon');
        $weather = $this->getWeatherInfo($lat, $lon);
        $photo = $this->getCityPhoto($lat, $lon);
        return response()->json(
            [
                'weather_info' => $weather,
                'photo' => $photo
            ],
            200
        );
    }

    private function getWeatherInfo($lat, $lon)
    {
        $client = new Client([
            'base_uri' => 'https://api.openweathermap.org/data/2.5/'
        ]);
        $weatherResponse = $client->get(
            'weather?'.
            'units=metric'.
            '&lat='.$lat.
            '&lon='.$lon.
            '&lang='.app()->getLocale().
            '&appid='.env('OPEN_WEATHER_MAP_API_KEY', 'y')
        );
        if ($weatherResponse->getStatusCode() == 200) {
            $weatherData = json_decode((string)$weatherResponse->getBody(), true);
            $icon = $weatherData['weather'][0]['icon'];
            $weatherData['weather'][0]['icon'] = 'https://openweathermap.org/img/wn/'.$icon.'@2x.png';
            $weatherData['weather'] = $weatherData['weather'][0];
            unset($weatherData['coord']);
            unset($weatherData['base']);
            unset($weatherData['dt']);
            unset($weatherData['timezone']);
            unset($weatherData['id']);
            unset($weatherData['cod']);
        } else {
            $e = new HttpException(400, 'Request limit is over, please try again later');
            $e->customCode=10;
            throw $e;
        }
        return $weatherData;
    }

    private function getCityPhoto($lat, $lon)
    {
        $client = new Client([
            'base_uri' => 'https://www.flickr.com/services/rest/'
        ]);
        $photoInfosResponse = $client->get(
            '?method=flickr.photos.search'.
            '&api_key='.env('FLICKR_API_KEY', 'y').
            '&accuracy=11'. //11 for city
            '&lat='.$lat.
            '&lon='.$lon.
            '&format=json'.
            '&nojsoncallback=1'
        );
        $photoInfos = json_decode((string)$photoInfosResponse->getBody(), true);
        $photos = $photoInfos['photos']['photo'];
        $randomPhotoId = $photos[rand(0, count($photos))]['id'];

        $photoResponse = $client->get(
            '?method=flickr.photos.getSizes'.
            '&api_key='.env('FLICKR_API_KEY', 'y').
            '&photo_id='.$randomPhotoId.
            '&format=json'.
            '&nojsoncallback=1'
        );
        $photo = json_decode((string)$photoResponse->getBody(), true);
        return [
            'url' => $photo['sizes']['size'][6]['source'],
        ];
    }
}
