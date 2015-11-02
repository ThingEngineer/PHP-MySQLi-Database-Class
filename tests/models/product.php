<?php
/**
 * To make IDEs autocomplete happy
 *
 * @property int id
 * @property int userid
 * @property int customerId
 * @property string productName
 */
class product extends dbObject {
    protected $dbTable = "products";
    protected $primaryKey = "id";
    protected $dbFields = Array (
        'userId' => Array('int', 'required'),
        'customerId' => Array ('int', 'required'),
        'productName' => Array ('text','required')
    );
    protected $relations = Array (
        'userId' => Array ("hasOne", "user"),
        'user' => Array ("hasOne", "user", "userId")
    );

    public function last () {
        $this->where ("id" , 130, '>');
        return $this;
    }
}


?>
