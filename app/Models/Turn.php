<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;
class Turn extends Model 
{
    use HasFactory;
    protected $connection = 'mongodb';
    /**
     * asociamos el nobre del la coleccion con el modelo
     */
    protected $collection = 'turns';
    /**
     * 
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'service_id', 'branch_id', 'assigned_cashier_id','turn_code','turn_number','status',
        'called_at','finished_at',
    ];
    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     * @var array
     */
    protected $casts = [
        'called_at' => 'datetime','finished_at' => 'datetime',
        'created_at' => 'datetime','updated_at' => 'datetime', 
    ];
    /**
     * Defino la relación: Un turno pertenece a un Usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Defino la relación: Un turno pertenece a un Servicio
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    /**
     * Defino la relación: Un turno pertenece a una Sucursal
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}