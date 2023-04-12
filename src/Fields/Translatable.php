<?php

namespace VI\MoonShineSpatieTranslatable\Fields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MoonShine\Fields\Fields;
use MoonShine\Fields\Json;
use MoonShine\Fields\Select;
use MoonShine\Fields\Text;

class Translatable extends Json
{
    protected array $languagesCodes = [
        "af", "sq", "am", "ar", "an", "hy", "ast", "az", "eu", "be", "bn", "bs", "br", "bg", "ca", "ckb", "zh", "zh-HK", "zh-CN", "zh-TW", "co", "hr", "cs", "da", "nl", "en", "en-AU", "en-CA", "en-IN", "en-NZ", "en-ZA", "en-GB", "en-US", "eo", "et", "fo", "fil", "fi", "fr", "fr-CA", "fr-FR", "fr-CH", "gl", "ka", "de", "de-AT", "de-DE", "de-LI", "de-CH", "el", "gn", "gu", "ha", "haw", "he", "hi", "hu", "is", "id", "ia", "ga", "it", "it-IT", "it-CH", "ja", "kn", "kk", "km", "ko", "ku", "ky", "lo", "la", "lv", "ln", "lt", "mk", "ms", "ml", "mt", "mr", "mn", "ne", "no", "nb", "nn", "oc", "or", "om", "ps", "fa", "pl", "pt", "pt-BR", "pt-PT", "pa", "qu", "ro", "mo", "rm", "ru", "gd", "sr", "sh", "sn", "sd", "si", "sk", "sl", "so", "st", "es", "es-AR", "es-419", "es-MX", "es-ES", "es-US", "su", "sw", "sv", "tg", "ta", "tt", "te", "th", "ti", "to", "tr", "tk", "tw", "uk", "ur", "ug", "uz", "vi", "wa", "cy", "fy", "xh", "yi", "yo", "zu",
    ];

    protected array $requiredLanguagesCodes = [];

    protected array $priorityLanguagesCodes = [];

    protected bool $keyValue = true;

    /**
     * @param array $languages
     * @return $this
     */
    public function requiredLanguages(array $languages): static
    {
        sort($languages);
        $this->requiredLanguagesCodes = $languages;

        return $this;
    }

    /**
     * @param array $languages
     * @return $this
     */
    public function priorityLanguages(array $languages): static
    {
        sort($languages);
        $this->priorityLanguagesCodes = $languages;

        return $this;
    }

    protected function getLanguagesCodes(): array
    {
        sort($this->languagesCodes);

        return collect(array_combine($this->requiredLanguagesCodes, $this->requiredLanguagesCodes))
            ->merge(array_combine($this->priorityLanguagesCodes, $this->priorityLanguagesCodes))
            ->merge(array_combine($this->languagesCodes, $this->languagesCodes))
            ->toArray();
    }

    public function keyValue(string $key = 'Language', string $value = 'Value'): static
    {
        $this->fields([
            Select::make($key, 'key')
                ->options($this->getLanguagesCodes())
                ->nullable(),
            Text::make($value, 'value'),
        ]);

        return $this;
    }

    public function getFields(): Fields
    {
        if (empty($this->fields)) {
            $this->fields([
                Select::make(__('Code'), 'key')
                    ->options(array_combine($this->getLanguagesCodes(), array_map(static fn ($code) => Str::upper($code), $this->getLanguagesCodes())))
                    ->nullable(),
                Text::make(__('Value'), 'value'),
            ]);
        }

        return parent::getFields();
    }

    public function hasFields(): bool
    {
        return true;
    }

    public function indexViewValue(Model $item, bool $container = false): string
    {
        $columns = [];

        $values = collect($item->getTranslations($this->field()))
            ->map(fn ($value, $key) => ['key' => $key, 'value' => $value])
            ->values();

        foreach ($this->getFields() as $field) {
            $columns[$field->field()] = $field->label();
        }

        return view('moonshine::ui.table', [
            'columns' => $columns,
            'values' => $values,
        ]);
    }

    public function exportViewValue(Model $item): string
    {
        return $item->getTranslation($this->field());
    }

    public function formViewValue(Model $item): mixed
    {
        return $item->getTranslations($this->field());
    }

    /**
     * @throws ValidationException
     */
    public function save(Model $item): Model
    {
        if ($this->isCanSave() && $this->requestValue() !== false) {
            $array = collect($this->requestValue())
                ->filter(fn ($data) => ! empty($data['key']) && ! empty($data['value']))
                ->mapWithKeys(fn ($data) => [$data['key'] => $data['value']])
                ->toArray();

            $notSetLanguages = array_diff($this->requiredLanguagesCodes, array_keys($array));

            if (! empty($notSetLanguages)) {
                throw ValidationException::withMessages(
                    [$this->field() =>
                        sprintf('The field %s does not have translation values set for the following languages: %s', $this->label(), implode(', ', $notSetLanguages)), ]
                );
            }

            $item->{$this->field()} = $array;
        }

        return $item;
    }
}