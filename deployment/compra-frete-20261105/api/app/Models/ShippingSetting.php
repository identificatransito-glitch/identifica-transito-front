<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShippingSetting extends Model {
 protected $table='shipping_settings';
 protected $fillable=['id','enabled','fixed_amount'];
 protected function casts(): array {return ['enabled'=>'boolean','fixed_amount'=>'decimal:2'];}
}
