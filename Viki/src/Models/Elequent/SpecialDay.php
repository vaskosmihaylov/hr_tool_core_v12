<?php

namespace viki\Service\Models\Elequent;


use \Illuminate\Database\Eloquent\Model;
use \Illuminate\Support\Carbon;

class SpecialDay extends Model
{

    const NO_WORKING       = 1;
    const WORKING          = 2;
    const ULRHOLIDAY       = "https://portal.combyte-bg.com/api/calendar/index.php";

    protected $table = 'viki_special_days';

    protected $fillable = [
        'date',
        'type',
        'comment',
    ];

    public $timestamps = false;

    public static function create(array $attributes  = array())
    {

        $model = Self::where('date', $attributes['date'])->where('comment', $attributes['comment'])->first();

        if(!$model) {
            $model = new static($attributes);
            $model->save();
        }
        else {
            $model->update($attributes);
        }

        return $model;
    }

	public static function insertHolidays($year = null)
    {
        $client = new \GuzzleHttp\Client();
        $year = $year ?? date('Y');
        $baseUrl = self::ULRHOLIDAY.'?year='.$year;
        $request = $client->get($baseUrl, ['verify' => false]);
        $response = $request->getBody()->getContents();
        $statusCode = $request->getStatusCode();
        $holidays = json_decode($response,true);
        $addedDate = false;
        
        if ($statusCode != 200) {
            
            session()->flash('error');
            return redirect()->back();
        } else {
            foreach ($holidays as $key=>$holidayD) {

                if (($holidayD != 'Събота') 
                    &&($holidayD != 'Неделя'))
                {   
                    $holidayExist = SpecialDay::where('date', '=', Carbon::parse($key)->toDateString())
                                    ->where('type',"=",1)
                                    ->count();

                    if ($holidayExist <= 0) {
                        $addedDate = true;
                        SpecialDay::create([
                            'date' => Carbon::parse($key)->toDateString(),
                            'type' => 1,
                            'comment' => $holidayD,
                        ]);
                    }
                }
            }
        }
        if ($addedDate) {
            return 'Добавени празници!';
        } else {
           return 'Не бяха открити събития за добавяне!';
        }

        return redirect()->back();
    }


    static public function getAllTypes()
    {
        return [
            self::NO_WORKING,
            self::WORKING,
        ];
    }
}
