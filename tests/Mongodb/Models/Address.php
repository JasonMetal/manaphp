<?php

namespace Tests\Mongodb\Models;

use ManaPHP\Mongodb\Model;

class Address extends Model
{
    public $address_id;
    public $address;
    public $address2;
    public $district;
    public $city_id;
    public $postal_code;
    public $phone;
    public $last_update;

    public function getFieldTypes()
    {
        return [
            'address_id'  => 'integer',
            'address'     => 'string',
            'address2'    => 'string',
            'district'    => 'string',
            'city_id'     => 'integer',
            'postal_code' => 'string',
            'phone'       => 'string',
            'last_update' => 'string'
        ];
    }
}