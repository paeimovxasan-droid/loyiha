<?php
declare(strict_types=1);

namespace Tortinmang\Core;

final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            $this->errors[$field] = "{$label} majburiy maydon";
        }
        return $this;
    }

    public function min(string $field, float $min, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = (float) ($this->data[$field] ?? 0);
        if ($value < $min) {
            $this->errors[$field] = "{$label} kamida " . number_format($min, 0, '', ' ') . " bo'lishi kerak";
        }
        return $this;
    }

    public function max(string $field, float $max, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = (float) ($this->data[$field] ?? 0);
        if ($value > $max) {
            $this->errors[$field] = "{$label} ko'pi bilan " . number_format($max, 0, '', ' ') . " bo'lishi kerak";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) < $min) {
            $this->errors[$field] = "{$label} kamida {$min} ta belgi bo'lishi kerak";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->errors[$field] = "{$label} ko'pi bilan {$max} ta belgi bo'lishi kerak";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!is_numeric($value)) {
            $this->errors[$field] = "{$label} raqam bo'lishi kerak";
        }
        return $this;
    }

    public function positive(string $field, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = (float) ($this->data[$field] ?? 0);
        if ($value <= 0) {
            $this->errors[$field] = "{$label} musbat son bo'lishi kerak";
        }
        return $this;
    }

    public function in(string $field, array $values, string $label = ''): static
    {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!in_array($value, $values, true)) {
            $this->errors[$field] = "{$label} noto'g'ri qiymat";
        }
        return $this;
    }

    public function cardNumber(string $field, string $label = 'Karta raqami'): static
    {
        $value = preg_replace('/\s+/', '', (string) ($this->data[$field] ?? ''));
        if (!preg_match('/^\d{16}$/', $value)) {
            $this->errors[$field] = "{$label} 16 raqamdan iborat bo'lishi kerak";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function firstError(): string
    {
        return array_values($this->errors)[0] ?? '';
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
