<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $connection = 'pgsql';
    protected $guarded    = ['id'];
    protected $casts      = ['variables' => 'array', 'is_active' => 'boolean'];

    public function render(array $vars): array
    {
        $subject = $this->subject;
        $html    = $this->html_body;
        $text    = $this->text_body ?? strip_tags($html);

        foreach ($vars as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
            $html    = str_replace('{{' . $key . '}}', (string) $value, $html);
            $text    = str_replace('{{' . $key . '}}', (string) $value, $text);
        }

        return compact('subject', 'html', 'text');
    }
}
