<?php

namespace viki\Service\Request;

use \viki\Service\Request\Request;



class RegionRequest extends Request {

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
            'status'            => 'required',
        );
    }

}
