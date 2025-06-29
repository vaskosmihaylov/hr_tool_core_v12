<?php

namespace viki\Service\Models\Elequent;

use \Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Comment extends Model
{
    
    protected $table = "viki_comments";

    protected $fillable = [
        'date',
        'comment',
        'approvement_id',
        'created_by',
       
    ];
	
	
	public static function create($comment, $id)
    {  

		$today = Carbon::now();
		$date =  Carbon::parse($today)->format('Y-m-d');
		
        $modelAttributes = array(
            'comment'                  => $comment,
            'date'                     => $date,
			'approvement_id'           => $id,
			'created_by'               => Auth::id()
        );

        $model = new static($modelAttributes);

        $model->save();

        return $model;
    }
    public function createdBy()
    {
         return $this->belongsTo('\App\User', 'created_by');
    }

    /**
     * Get the  the approvement
    */
    public function approvement()
    {
        return $this->belongsTo('viki\Service\Models\Elequent\Approvement','approvement_id');
    }
 }