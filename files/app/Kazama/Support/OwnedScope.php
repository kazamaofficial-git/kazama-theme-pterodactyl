<?php

namespace Pterodactyl\Kazama\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Membatasi daftar server/user yang terlihat oleh admin terbatas (hanya yang ia buat). */
class OwnedScope implements Scope
{
    public function __construct(private string $kind)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $actor = Restrict::actor();
        if (!$actor) {
            return;
        }

        $table = $model->getTable();
        if ($this->kind === 'server') {
            $builder->where($table . '.kazama_created_by', $actor->id);

            return;
        }

        $builder->where(function ($q) use ($table, $actor) {
            $q->where($table . '.kazama_created_by', $actor->id)->orWhere($table . '.id', $actor->id);
        });
    }
}
