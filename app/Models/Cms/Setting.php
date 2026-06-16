<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;

/**
 * Linha de settings polimórfica (estilo media). O par (settable_type, settable_id)
 * identifica o owner: ('general', 0) para o bucket geral, ou (FQCN do model, 0) para
 * settings ao nível do tipo. Acesso via App\Support\Settings / trait HasSettings.
 */
class Setting extends Model
{
    protected $table = 'cms_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
