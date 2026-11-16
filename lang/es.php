<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

$strings['plugin_title'] = 'eXeLearning';
$strings['plugin_comment'] = 'Sube, visualiza y edita paquetes de eXeLearning v4 (.elpx) en los cursos.';

$strings['editor_enabled'] = 'Activar el editor integrado';
$strings['editor_enabled_help'] = 'Si se desactiva, los paquetes se pueden subir y ver, pero no crear ni editar en Chamilo (modo solo reproductor).';
$strings['max_upload_size_mb'] = 'Tamaño máximo del paquete (MB)';
$strings['max_upload_size_mb_help'] = 'Déjalo vacío o en 0 para usar el límite de subida de PHP. El límite de PHP se aplica siempre.';
$strings['edit_roles'] = 'Quién puede gestionar y editar paquetes';
$strings['edit_roles_help'] = 'Los administradores de la plataforma siempre pueden gestionar paquetes. Los estudiantes nunca.';
$strings['edit_roles_teachers'] = 'Profesorado del curso';
$strings['edit_roles_teachers_coaches'] = 'Profesorado del curso y tutores de sesión';
$strings['edit_roles_admins'] = 'Solo administradores de la plataforma';
$strings['defaultVisibilityInCourseHomepage'] = 'Visibilidad por defecto de la herramienta en la página del curso';
$strings['defaultVisibilityInCourseHomepage_help'] = 'Si la herramienta eXeLearning es visible para el alumnado en los cursos nuevos.';

$strings['upload_package'] = 'Subir un paquete';
$strings['package_file'] = 'Archivo de eXeLearning';
$strings['package_file_help'] = 'Paquetes de eXeLearning v4 (.elpx). Los archivos antiguos .elp deben abrirse y guardarse antes en eXeLearning 4.';
$strings['title'] = 'Título';
$strings['title_optional'] = 'Título (opcional)';
$strings['upload'] = 'Subir';
$strings['new_package'] = 'Crear un paquete nuevo';
$strings['no_packages'] = 'Todavía no hay paquetes de eXeLearning.';
$strings['language'] = 'Idioma';
$strings['updated'] = 'Última modificación';
$strings['size'] = 'Tamaño';
$strings['actions'] = 'Acciones';
$strings['edit'] = 'Editar';
$strings['download'] = 'Descargar';
$strings['rename'] = 'Renombrar';
$strings['delete'] = 'Eliminar';
$strings['show'] = 'Mostrar';
$strings['hide'] = 'Ocultar';
$strings['hidden'] = 'oculto';
$strings['confirm_delete'] = '¿Eliminar este paquete y todos sus archivos? No se puede deshacer.';
$strings['back_to_list'] = 'Volver a la lista';
$strings['fullscreen'] = 'Pantalla completa';

$strings['package_uploaded'] = 'Paquete subido.';
$strings['package_renamed'] = 'Paquete renombrado.';
$strings['package_deleted'] = 'Paquete eliminado.';
$strings['package_saved'] = 'Guardado.';
$strings['visibility_changed'] = 'Visibilidad actualizada.';
$strings['title_required'] = 'El título no puede estar vacío.';
$strings['not_allowed'] = 'No tienes permiso para hacer esto.';

$strings['save'] = 'Guardar';
$strings['close'] = 'Cerrar';
$strings['saving'] = 'Guardando…';
$strings['editor_loading'] = 'Cargando el editor…';
$strings['save_failed'] = 'No se ha podido guardar el paquete. La versión anterior no ha cambiado.';
$strings['save_conflict'] = 'Otra persona ha modificado este paquete desde que lo abriste. Recarga el editor para continuar.';
$strings['unsaved_changes'] = 'Tienes cambios sin guardar. ¿Cerrar el editor de todos modos?';
$strings['editor_disabled'] = 'El editor integrado está desactivado en esta plataforma.';

$strings['error_csrf'] = 'Tu sesión ha caducado o el formulario se envió dos veces. Inténtalo de nuevo.';
$strings['error_upload'] = 'No se ha recibido ningún archivo. Elige un paquete e inténtalo de nuevo.';
$strings['error_invalid_zip'] = 'El archivo no es un ZIP válido o está dañado.';
$strings['error_legacy_elp'] = 'Es un archivo antiguo de eXeLearning (.elp). Ábrelo en eXeLearning 4, guárdalo como .elpx y vuelve a subirlo.';
$strings['error_not_exelearning'] = 'Este archivo no es un paquete de eXeLearning v4 (falta content.xml o no es válido).';
$strings['error_missing_index'] = 'El paquete no tiene index.html. Vuelve a exportarlo desde eXeLearning 4.';
$strings['error_unsafe_path'] = 'El paquete contiene archivos con nombres no seguros y se ha rechazado.';
$strings['error_symlink'] = 'El paquete contiene enlaces simbólicos y se ha rechazado.';
$strings['error_too_many_entries'] = 'El paquete contiene demasiados archivos.';
$strings['error_too_large'] = 'El paquete es demasiado grande.';
$strings['error_compression_ratio'] = 'El paquete está comprimido de forma sospechosa y se ha rechazado.';
$strings['error_bad_extension'] = 'Solo se aceptan paquetes de eXeLearning .elpx (o .zip).';
$strings['error_storage'] = 'No se ha podido guardar el paquete. Inténtalo más tarde.';
