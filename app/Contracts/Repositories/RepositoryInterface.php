<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTO\ListQuery;
use App\DTO\ShowQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contrato común para repositorios con soporte completo para Index,
 * operaciones masivas y utilidades de concurrencia.
 *
 * Define firmas exactas para operaciones CRUD, búsqueda, filtrado,
 * paginación y manipulación masiva de registros.
 */
interface RepositoryInterface
{
    // === LISTADO (INDEX) ===

    /**
     * Pagina registros aplicando búsqueda, filtros y ordenamiento.
     *
     * @param  ListQuery  $query  Parámetros de consulta normalizados
     * @param  array<string>  $with  Relaciones a cargar eagerly
     * @param  array<string>  $withCount  Relaciones para contar
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(ListQuery $query, array $with = [], array $withCount = []): LengthAwarePaginator;

    /**
     * Obtiene todos los registros sin paginación.
     *
     * @param  array<string>  $columns  Columnas a seleccionar
     * @return Collection<int, Model>
     */
    public function all(array $columns = ['*']): Collection;

    // === POR SUBCONJUNTO DE IDS (INDEX "SELECCIONADOS") ===

    /**
     * Pagina registros por IDs específicos en orden descendente.
     *
     * @param  array<int|string>  $ids  IDs a incluir
     * @param  int  $perPage  Registros por página
     * @param  array<string>  $with  Relaciones a cargar eagerly
     * @param  array<string>  $withCount  Relaciones para contar
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginateByIdsDesc(array $ids, int $perPage, array $with = [], array $withCount = []): LengthAwarePaginator;

    // === BÚSQUEDAS PUNTUALES ===

    /**
     * Busca un registro por ID.
     *
     * @param  int|string  $id  ID del registro
     * @param  array<string>  $with  Relaciones a cargar eagerly
     */
    public function findById(int|string $id, array $with = []): ?Model;

    /**
     * Busca un registro por ID o lanza excepción.
     *
     * @param  int|string  $id  ID del registro
     * @param  array<string>  $with  Relaciones a cargar eagerly
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFailById(int|string $id, array $with = []): Model;

    /**
     * Busca un registro por UUID.
     *
     * @param  string  $uuid  UUID del registro
     * @param  array<string>  $with  Relaciones a cargar eagerly
     */
    public function findByUuid(string $uuid, array $with = []): ?Model;

    /**
     * Busca un registro por UUID o lanza excepción.
     *
     * @param  string  $uuid  UUID del registro
     * @param  array<string>  $with  Relaciones a cargar eagerly
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFailByUuid(string $uuid, array $with = []): Model;

    // === SHOW (DETALLE) ===

    /**
     * Muestra un registro por ID aplicando parámetros de ShowQuery.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function showById(int|string $id, ShowQuery $query): Model;

    /**
     * Muestra un registro por UUID aplicando parámetros de ShowQuery.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function showByUuid(string $uuid, ShowQuery $query): Model;

    // === EXISTENCIA / CONTEOS ===

    /**
     * Verifica si existe un registro por ID.
     *
     * @param  int|string  $id  ID del registro
     */
    public function existsById(int|string $id): bool;

    /**
     * Verifica si existe un registro por UUID.
     *
     * @param  string  $uuid  UUID del registro
     */
    public function existsByUuid(string $uuid): bool;

    /**
     * Cuenta registros aplicando filtros opcionales.
     *
     * @param  array<string, mixed>  $filters  Filtros a aplicar
     */
    public function count(array $filters = []): int;

    // === ESCRITURA ===

    /**
     * Crea un nuevo registro.
     *
     * @param  array<string, mixed>  $attributes  Atributos del registro
     */
    public function create(array $attributes): Model;

    /**
     * Crea múltiples registros.
     *
     * @param  array<array<string, mixed>>  $rows  Filas a insertar
     * @return Collection<int, Model>
     */
    public function createMany(array $rows): Collection;

    /**
     * Actualiza un registro existente.
     *
     * @param  Model|int|string  $modelOrId  Modelo o ID del registro
     * @param  array<string, mixed>  $attributes  Atributos a actualizar
     */
    public function update(Model|int|string $modelOrId, array $attributes): Model;

    /**
     * Inserta o actualiza registros (upsert).
     *
     * @param  array<array<string, mixed>>  $rows  Filas a procesar
     * @param  array<string>  $uniqueBy  Columnas para determinar unicidad
     * @param  array<string>  $updateColumns  Columnas a actualizar si existe
     * @return int Número de registros afectados
     */
    public function upsert(array $rows, array $uniqueBy, array $updateColumns): int;

    // === BORRADO / RESTAURACIÓN ===

    /**
     * Elimina un registro (soft delete si aplica).
     *
     * @param  Model|int|string  $modelOrId  Modelo o ID del registro
     */
    public function delete(Model|int|string $modelOrId): bool;

    /**
     * Elimina permanentemente un registro.
     *
     * @param  Model|int|string  $modelOrId  Modelo o ID del registro
     */
    public function forceDelete(Model|int|string $modelOrId): bool;

    /**
     * Restaura un registro eliminado (soft delete).
     *
     * @param  Model|int|string  $modelOrId  Modelo o ID del registro
     */
    public function restore(Model|int|string $modelOrId): bool;

    // === ESTADO COMÚN ===

    /**
     * Establece el estado activo/inactivo de un registro.
     *
     * @param  Model|int|string  $modelOrId  Modelo o ID del registro
     * @param  bool  $active  Estado activo
     */
    public function setActive(Model|int|string $modelOrId, bool $active): Model;

    // === OPERACIONES MASIVAS (INDEX) ===

    /**
     * Elimina múltiples registros por IDs (soft delete si aplica).
     *
     * @param  array<int|string>  $ids  IDs a eliminar
     * @return int Número de registros afectados
     */
    public function bulkDeleteByIds(array $ids): int;

    /**
     * Elimina permanentemente múltiples registros por IDs.
     *
     * @param  array<int|string>  $ids  IDs a eliminar
     * @return int Número de registros afectados
     */
    public function bulkForceDeleteByIds(array $ids): int;

    /**
     * Restaura múltiples registros por IDs.
     *
     * @param  array<int|string>  $ids  IDs a restaurar
     * @return int Número de registros afectados
     */
    public function bulkRestoreByIds(array $ids): int;

    /**
     * Establece el estado activo de múltiples registros por IDs.
     *
     * @param  array<int|string>  $ids  IDs a actualizar
     * @param  bool  $active  Estado activo
     * @return int Número de registros afectados
     */
    public function bulkSetActiveByIds(array $ids, bool $active): int;

    /**
     * Elimina múltiples registros por UUIDs (soft delete si aplica).
     *
     * @param  array<string>  $uuids  UUIDs a eliminar
     * @return int Número de registros afectados
     */
    public function bulkDeleteByUuids(array $uuids): int;

    /**
     * Elimina permanentemente múltiples registros por UUIDs.
     *
     * @param  array<string>  $uuids  UUIDs a eliminar
     * @return int Número de registros afectados
     */
    public function bulkForceDeleteByUuids(array $uuids): int;

    /**
     * Restaura múltiples registros por UUIDs.
     *
     * @param  array<string>  $uuids  UUIDs a restaurar
     * @return int Número de registros afectados
     */
    public function bulkRestoreByUuids(array $uuids): int;

    /**
     * Establece el estado activo de múltiples registros por UUIDs.
     *
     * @param  array<string>  $uuids  UUIDs a actualizar
     * @param  bool  $active  Estado activo
     * @return int Número de registros afectados
     */
    public function bulkSetActiveByUuids(array $uuids, bool $active): int;

    // === CONCURRENCIA (PESSIMISTIC LOCK) ===

    /**
     * Ejecuta un callback con bloqueo pesimista sobre un registro por ID.
     *
     * @param  int|string  $id  ID del registro
     * @param  callable  $callback  Función a ejecutar con el registro bloqueado
     * @return mixed Resultado del callback
     */
    public function withPessimisticLockById(int|string $id, callable $callback): mixed;

    /**
     * Ejecuta un callback con bloqueo pesimista sobre un registro por UUID.
     *
     * @param  string  $uuid  UUID del registro
     * @param  callable  $callback  Función a ejecutar con el registro bloqueado
     * @return mixed Resultado del callback
     */
    public function withPessimisticLockByUuid(string $uuid, callable $callback): mixed;
}
