import { t } from '@/lib/i18n';

export interface ImportErrorHint {
    title: string;
    hint: string;
}

export function importErrorHint(
    code: string | null,
    context: Record<string, unknown> | null,
): ImportErrorHint | null {
    if (!code) {
        return null;
    }

    switch (code) {
        case 'import.lua_global_not_found':
            return {
                title: t('This is not a WhereIveBeen file'),
                hint: t(
                    'Pick WhereIveBeen.lua from WTF/Account/<ACCOUNT>/SavedVariables/.',
                ),
            };
        case 'import.lua_syntax':
        case 'import.lua_bad_number':
            return {
                title: t('The file is damaged'),
                hint: context?.line
                    ? t(
                          'Line :line. Type /reload in game and upload it again.',
                          {
                              line: String(context.line),
                          },
                      )
                    : t('Type /reload in game and upload the file again.'),
            };
        case 'import.lua_no_sessions':
            return {
                title: t('The file holds no sessions'),
                hint: t(
                    'Play for a while, log out, then upload the file again.',
                ),
            };
        case 'import.lua_too_deep':
        case 'import.file_too_large':
        case 'import.lua_too_large':
        case 'import.too_many_sessions':
        case 'import.too_many_points':
            return {
                title: t('The file is too large to import'),
                hint: t(
                    'Delete older sessions in game with /wivbn prune and try again.',
                ),
            };
        case 'import.file_unreadable':
            return {
                title: t('The file could not be read'),
                hint: t('Upload it again.'),
            };
        case 'import.empty_input':
            return {
                title: t('Nothing was pasted'),
                hint: t('Copy the whole line from the addon export window.'),
            };
        case 'import.invalid_base64':
        case 'import.invalid_compressed':
        case 'import.invalid_json':
            return {
                title: t('The pasted line is broken'),
                hint: t(
                    'Copy it again with Ctrl+C from the export window — it must be copied whole.',
                ),
            };
        case 'import.validation_failed':
            return {
                title: t('The session data is not what the site expects'),
                hint: context?.fields
                    ? t('Unexpected fields: :fields.', {
                          fields: String(context.fields),
                      })
                    : t('Update the addon and export the session again.'),
            };
        case 'import.unknown_map':
            return {
                title: t('This zone is not on the site yet'),
                hint: t('The route was saved, but the map cannot be drawn.'),
            };
        case 'import.duplicate_session':
            return {
                title: t('This session is already imported'),
                hint: t('Nothing to do.'),
            };
        case 'import.session_limit':
            return {
                title: t('Session limit reached'),
                hint: t('Delete an older session to import a new one.'),
            };
        case 'import.value_out_of_range':
            return {
                title: t('The session holds impossible values'),
                hint: t('Update the addon and export the session again.'),
            };
        case 'import.database_error':
        case 'import.unexpected':
        default:
            return {
                title: t('The import failed'),
                hint: t('Try again — if it keeps failing, report the problem.'),
            };
    }
}
