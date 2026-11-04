<?php
namespace App\Services;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehiclePlateGuard
{
    public static function normalize(string $plate): string
    {
        return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    public static function matching(string $plate)
    {
        return Vehicle::query()->whereRaw("REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') = ?", [self::normalize($plate)]);
    }

    public static function save(string $plate, ?int $ignoreId, callable $save)
    {
        $plate=self::normalize($plate);
        if (!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plate)) {
            throw ValidationException::withMessages(['plate'=>'Informe uma placa válida.']);
        }
        $connection=DB::connection();
        $lock='it:vehicle-plate:'.substr(hash('sha256', $connection->getDatabaseName().':'.$plate),0,40);
        // MySQL advisory locks serialize competing requests even when no vehicle exists yet.
        $mysql=$connection->getDriverName()==='mysql';
        if (!$mysql && !($connection->getDriverName()==='sqlite' && $connection->getDatabaseName()===':memory:')) {
            throw new \RuntimeException('Unsupported vehicle plate lock driver.');
        }
        if ($mysql && (int)($connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired',[$lock])->acquired??0)!==1) {
            throw ValidationException::withMessages(['plate'=>'Esta placa está sendo salva. Aguarde e tente novamente.']);
        }
        try {
            return $connection->transaction(function () use ($plate,$ignoreId,$save) {
                $query=self::matching($plate);
                if ($ignoreId!==null) $query->where('id','!=',$ignoreId);
                if ($query->exists()) {
                    throw ValidationException::withMessages(['plate'=>'Esta placa já possui uma etiqueta cadastrada. Edite o cadastro existente em Meus veículos.']);
                }
                return $save($plate);
            });
        } finally {
            if ($mysql) $connection->selectOne('SELECT RELEASE_LOCK(?) AS released',[$lock]);
        }
    }

    public static function ensureSingleRegistration(Vehicle $vehicle): void
    {
        if (self::matching($vehicle->plate)->where('id','!=',$vehicle->id)->exists()) {
            throw ValidationException::withMessages(['vehicles'=>'Esta placa possui cadastros duplicados. Mantenha apenas um cadastro em Meus veículos antes de comprar o kit.']);
        }
    }
}
