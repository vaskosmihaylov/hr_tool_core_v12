<?php

namespace viki\Service\Request;

use \viki\Service\Request\Request;



class VacationRequest extends Request {
	
	/**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

	public function rules() {	
		
			return [
				
				'start_date'     => 'required|date',
				'end_date'       => 'required|date',											
				'type'          => 'required',
				'comment'       => 'required|max:255'

			];
	}

}