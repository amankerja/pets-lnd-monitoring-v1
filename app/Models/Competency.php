<?php

namespace App\Models;

/**
 * Alias Model `Competency` yang menunjuk langsung ke tabel `skills`
 * agar kompatibel penuh dengan penamaan `Skill` maupun `Competency`.
 */
class Competency extends Skill
{
    protected $table = 'skills';
}

