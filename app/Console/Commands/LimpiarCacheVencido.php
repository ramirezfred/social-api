<?php
// app/Console/Commands/LimpiarCacheVencido.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LimpiarCacheVencido extends Command
{
    protected $signature   = 'cache:limpiar-vencidos';
    protected $description = 'Elimina archivos de caché vencidos del disco';

    public function handle()
    {
        $directorio = storage_path('framework/cache/data');
        $eliminados = 0;
        $errores    = 0;

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directorio, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($archivos as $archivo) {
            if (!$archivo->isFile()) continue;

            try {
                $contenido = file_get_contents($archivo->getPathname());

                // Laravel guarda el timestamp de expiración en los primeros 10 bytes
                $expiracion = (int) substr($contenido, 0, 10);

                // 0 significa que nunca vence
                if ($expiracion !== 0 && $expiracion < time()) {
                    unlink($archivo->getPathname());
                    $eliminados++;
                }

            } catch (\Exception $e) {
                $errores++;
            }
        }

        $this->info("Archivos eliminados: {$eliminados}");

        if ($errores > 0) {
            $this->warn("Errores al procesar: {$errores}");
        }

        return Command::SUCCESS;
    }
}