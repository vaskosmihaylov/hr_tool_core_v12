<?php

namespace viki\Service\Request;

use \viki\Service\Request\Request;



class WorkPlaceActivityRequest extends Request {

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

            'activity'          => 'min:2|max:55',
            'type_working'      => 'required',
            'neto_salary'       => 'not_in:0|regex:/^\d*(\.\d{1,5})?$/',
            'social_plus'       => 'not_in:0|regex:/^\d*(\.\d{1,5})?$/',
            'worker_count'     => 'required|numeric|min:1|max:100',
        );
    }

}
