<?php

// DAIRY Etapa 22: regenera los triggers del historial de cambios para incluir columnas agregadas después de 0278
// (p. ej. products.storage_class de 0280). Solo reemplaza triggers.

\Grocy\Helpers\DairyChangeLog::InstallTriggers(\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw());
