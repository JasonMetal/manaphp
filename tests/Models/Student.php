<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 2015/12/28
 * Time: 0:05
 */
namespace Tests\Models;

use ManaPHP\Db\Model;

class Student extends Model
{
    public $id;
    public $age;
    public $name;

    public function getTable($context = null)
    {
        return '_student';
    }
}