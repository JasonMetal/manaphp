<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 2015/12/28
 * Time: 0:05
 */

namespace Tests\Models;

use ManaPHP\Data\Db\Model;

class Store extends Model
{
    public $store_id;
    public $manager_staff_id;
    public $address_id;
    public $last_update;
}