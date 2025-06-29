<?php

namespace viki\Service\Request;

use \viki\Service\Request\Request;



class WorkerRequest extends Request {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {

        return array(

            'name'              => 'required|min:2|max:55',
            'middle_name'       => 'required|min:2|max:55',
            'family_name'       => 'required|min:2|max:55',
            'egn'               => 'string|regex:/^[0-9]*$/|unique:viki_workers|min:10|max:10',
            'start_date'        => 'required|date',
            'type_working'      => 'required',
            'neto_salary'       => 'regex:/^\d*(\.\d{1,3})?$/',
            'hours_per_day'     => 'required|min:1',
            'region_id'         => 'required',
            'note'              => 'max:350'
            
        );
    }

}
