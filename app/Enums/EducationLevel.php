<?php

namespace App\Enums;

enum EducationLevel: string
{
    case Mahasiswa = 'mahasiswa';
    case Sma = 'sma';
    case Smk = 'smk';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::Sma => 'SMA',
            self::Smk => 'SMK',
        };
    }

    /**
     * Form labels for the fixed cover fields (schema keys stay name, nim, prodi, campus).
     *
     * @return array{name: string, nim: string, prodi: string, campus: string}
     */
    public function coverLabels(): array
    {
        return match ($this) {
            self::Mahasiswa => ['name' => 'Nama lengkap', 'nim' => 'NIM', 'prodi' => 'Program studi', 'campus' => 'Kampus'],
            self::Sma => ['name' => 'Nama lengkap', 'nim' => 'NISN/NIS', 'prodi' => 'Kelas', 'campus' => 'Sekolah'],
            self::Smk => ['name' => 'Nama lengkap', 'nim' => 'NISN/NIS', 'prodi' => 'Kelas/Jurusan', 'campus' => 'Sekolah'],
        };
    }

    public function institutionLabel(): string
    {
        return $this === self::Mahasiswa ? 'Kampus' : 'Sekolah';
    }

    /**
     * Cover text: the person line and the footer lines (group, institution, year).
     *
     * @param  array<string, mixed>  $cover
     * @return array{person: string, foot: array<int, string>}
     */
    public function cover(array $cover): array
    {
        $prefix = $this === self::Mahasiswa ? 'PROGRAM STUDI ' : 'KELAS ';

        return [
            'person' => trim(($cover['name'] ?? '').' '.(isset($cover['nim']) ? "({$cover['nim']})" : '')),
            'foot' => array_values(array_filter([
                isset($cover['prodi']) ? $prefix.$cover['prodi'] : null,
                $cover['campus'] ?? null,
                $cover['year'] ?? null,
            ])),
        ];
    }
}
