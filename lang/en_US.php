<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

$strings['plugin_title'] = 'eXeLearning';
$strings['plugin_comment'] = 'Upload, view and edit eXeLearning v4 packages (.elpx) in courses.';

$strings['editor_enabled'] = 'Enable the embedded editor';
$strings['editor_enabled_help'] = 'When disabled, packages can be uploaded and viewed but not created or edited in Chamilo (player-only mode).';
$strings['max_upload_size_mb'] = 'Maximum package size (MB)';
$strings['max_upload_size_mb_help'] = 'Leave empty or 0 to use the PHP upload limit. The PHP limit always applies.';
$strings['edit_roles'] = 'Who can manage and edit packages';
$strings['edit_roles_help'] = 'Platform administrators can always manage packages. Students never can.';
$strings['edit_roles_teachers'] = 'Course teachers';
$strings['edit_roles_teachers_coaches'] = 'Course teachers and session coaches';
$strings['edit_roles_admins'] = 'Platform administrators only';
$strings['defaultVisibilityInCourseHomepage'] = 'Default visibility of the tool on the course homepage';
$strings['defaultVisibilityInCourseHomepage_help'] = 'Whether the eXeLearning tool is visible to students in new courses.';

$strings['upload_package'] = 'Upload a package';
$strings['package_file'] = 'eXeLearning file';
$strings['package_file_help'] = 'eXeLearning v4 packages (.elpx). Legacy .elp files must be opened and saved again in eXeLearning 4 first.';
$strings['title'] = 'Title';
$strings['title_optional'] = 'Title (optional)';
$strings['upload'] = 'Upload';
$strings['new_package'] = 'Create a new package';
$strings['no_packages'] = 'There are no eXeLearning packages here yet.';
$strings['language'] = 'Language';
$strings['updated'] = 'Last updated';
$strings['size'] = 'Size';
$strings['actions'] = 'Actions';
$strings['edit'] = 'Edit';
$strings['download'] = 'Download';
$strings['rename'] = 'Rename';
$strings['delete'] = 'Delete';
$strings['show'] = 'Show';
$strings['hide'] = 'Hide';
$strings['hidden'] = 'hidden';
$strings['confirm_delete'] = 'Delete this package and all its files? This cannot be undone.';
$strings['back_to_list'] = 'Back to the list';
$strings['fullscreen'] = 'Fullscreen';

$strings['package_uploaded'] = 'Package uploaded.';
$strings['package_renamed'] = 'Package renamed.';
$strings['package_deleted'] = 'Package deleted.';
$strings['package_saved'] = 'Saved.';
$strings['visibility_changed'] = 'Visibility updated.';
$strings['title_required'] = 'The title cannot be empty.';
$strings['not_allowed'] = 'You are not allowed to do this.';

$strings['save'] = 'Save';
$strings['close'] = 'Close';
$strings['saving'] = 'Saving…';
$strings['editor_loading'] = 'Loading the editor…';
$strings['save_failed'] = 'The package could not be saved. Your previous version is unchanged.';
$strings['save_conflict'] = 'This package was changed by someone else since you opened it. Reload the editor to continue.';
$strings['unsaved_changes'] = 'You have unsaved changes. Close the editor anyway?';
$strings['editor_disabled'] = 'The embedded editor is disabled on this platform.';

$strings['error_csrf'] = 'Your session expired or the form was sent twice. Please try again.';
$strings['error_upload'] = 'No file was received. Please choose a package and try again.';
$strings['error_invalid_zip'] = 'The file is not a valid ZIP archive or it is damaged.';
$strings['error_legacy_elp'] = 'This is a legacy eXeLearning (.elp) file. Open it in eXeLearning 4 and save it as .elpx, then upload it again.';
$strings['error_not_exelearning'] = 'This archive is not an eXeLearning v4 package (content.xml is missing or invalid).';
$strings['error_missing_index'] = 'The package has no index.html. Export it again from eXeLearning 4.';
$strings['error_unsafe_path'] = 'The package contains files with unsafe names and was rejected.';
$strings['error_symlink'] = 'The package contains symbolic links and was rejected.';
$strings['error_too_many_entries'] = 'The package contains too many files.';
$strings['error_too_large'] = 'The package is too large.';
$strings['error_compression_ratio'] = 'The package is compressed in a suspicious way and was rejected.';
$strings['error_bad_extension'] = 'Only .elpx (or .zip) eXeLearning packages are accepted.';
$strings['error_storage'] = 'The package could not be stored. Please try again later.';
