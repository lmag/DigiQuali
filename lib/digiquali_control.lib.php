<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/digiquali_control.lib.php
 * \ingroup digiquali
 * \brief   Library files with common functions for Control.
 */

// Load Saturne libraries.
require_once __DIR__ . '/../../saturne/lib/object.lib.php';

/**
 * Prepare array of tabs for control.
 *
 * @param  Control $object Control object.
 * @return array           Array of tabs.
 * @throws Exception
 */
function control_prepare_head(Control $object): array
{
    // Global variables definitions.
    global $conf, $db, $langs;

    $head[1][0] = dol_buildpath('/digiquali/view/object_medias.php', 1) . '?id=' . $object->id . '&object_type=control';
    $head[1][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-file-image pictofixedwidth"></i>' . $langs->trans('Medias') : '<i class="fas fa-file-image"></i>';
    $head[1][2] = 'medias';

	// Initialize technical objects
	$controlEquipment = new ControlEquipment($db);

	$controlEquipmentArray = $controlEquipment->fetchFromParent($object->id);
	if (is_array($controlEquipmentArray) && !empty($controlEquipmentArray)) {
		$nbEquipment = count($controlEquipmentArray);
	} else {
		$nbEquipment = 0;
	}

	$head[2][0]  = dol_buildpath('/digiquali/view/control/control_equipment.php', 1) . '?id=' . $object->id;
	$head[2][1]  = $conf->browser->layout != 'phone' ? '<i class="fas fa-toolbox pictofixedwidth"></i>' . $langs->trans('ControlEquipment') : '<i class="fas fa-toolbox"></i>';
    $head[2][1] .= '<span class="badge marginleftonlyshort">' . $nbEquipment . '</span>';
	$head[2][2]  = 'equipment';

	$moreparam['documentType']       = 'ControlDocument';
    $moreparam['attendantTableMode'] = 'simple';

    return saturne_object_prepare_head($object, $head, $moreparam, true);
}

/**
 * Get linked object infos
 *
 * @param  CommonObject $linkedObject     Linked object (product, productlot, project, etc.)
 * @param  array        $linkableElements Array of linkable elements infos (product, productlot, project, etc.)
 * @return array        $out              Array of linked object infos to display on public interface
 * @see    get_sheet_linkable_objects()   Get linkable objects for sheet for example (product, productlot, project, etc.)
 */
function get_linked_object_infos(CommonObject $linkedObject, array $linkableElements): array
{
    global $conf, $db, $langs;

    $linkableElement = $linkableElements[$linkedObject->element];

    // TODO: see if we can remove this if
    $modulePart = $linkedObject->element;
    if ($linkedObject->element == 'product') {
        $modulePart = 'produit';
    }
    if ($linkedObject->element == 'productlot') {
        $linkedObject->element = 'productbatch';
    }

    $out['linkedObject']['image'] = saturne_show_medias_linked($modulePart, $conf->{$linkedObject->element}->multidir_output[$conf->entity] . '/' . $linkedObject->ref . '/', 'small', 1, 0, 0, 0, 100, 100, 0, 0, 1,  $linkedObject->ref . '/', $linkedObject, 'photo', 0, 0,0, 1);
    if ($linkedObject->element == 'productbatch') {
        $linkedObject->element = 'productlot';
    }

    $out['linkedObject']['title']        = $langs->transnoentities($linkableElement['langs']);
    $out['linkedObject']['name_field']   = img_picto('', $linkableElement['picto'], 'class="pictofixedwidth"') . $linkedObject->{$linkableElement['name_field']};
    $out['linkedObject']['qc_frequency'] = '<i class="objet-icon fas fa-history"></i>' . $linkedObject->array_options['options_qc_frequency'] . ' ' . $langs->transnoentities('Days');

    if (isset($linkableElement['fk_parent'])) {
        $linkedObjectParentData = [];
        foreach ($linkableElements as $value) {
            if (isset($value['post_name']) && $value['post_name'] === $linkableElement['fk_parent']) {
                $linkedObjectParentData = $value;
                break;
            }
        }

        if (!empty($linkedObjectParentData['class_path'])) {
            require_once DOL_DOCUMENT_ROOT . '/' . $linkedObjectParentData['class_path'];

            $parentLinkedObject = new $linkedObjectParentData['className']($db);

            $parentLinkedObject->fetch($linkedObject->{$linkableElement['fk_parent']});

            // TODO: see if we can remove this if
            $modulePart = $parentLinkedObject->element;
            if ($parentLinkedObject->element == 'product') {
                $modulePart = 'produit';
            }

            $out['parentLinkedObject']['image']      = saturne_show_medias_linked($modulePart, $conf->{$parentLinkedObject->element}->multidir_output[$conf->entity] . '/' . $parentLinkedObject->ref . '/', 'small', 1, 0, 0, 0, 100, 100, 0, 0, 1,  $parentLinkedObject->ref . '/', $parentLinkedObject, 'photo', 0, 0,0, 1);
            $out['parentLinkedObject']['title']      = $langs->transnoentities($linkedObjectParentData['langs']);
            $out['parentLinkedObject']['name_field'] = img_picto('', $linkedObjectParentData['picto'], 'class="pictofixedwidth"') . $parentLinkedObject->{$linkedObjectParentData['name_field']};
        }
    }

    return $out;
}

/**
 * Get control infos
 *
 * @param  CommonObject $linkedObject Linked object (product, productlot, project, etc.)
 * @return array  $out                Array of control infos to display on public interface
 */
function get_control_infos(CommonObject $linkedObject): array
{
    global $db, $langs;

    $out         = [];
    $lastControl = null;
    foreach ($linkedObject->linkedObjects['digiquali_control'] as $control) {
        if ($control->status < Control::STATUS_LOCKED ||empty($control->control_date)) {
            continue;
        }

        if ($lastControl === null || $control->control_date > $lastControl->control_date) {
            $lastControl = $control;
            $out['LastControl']['title']        = $langs->transnoentities(dol_ucfirst($lastControl->element));
            $out['LastControl']['ref']          = img_picto('', $lastControl->picto, 'class="pictofixedwidth"') . $lastControl->ref;
            $out['LastControl']['control_date'] = '<i class="objet-icon far fa-calendar"></i>' . dol_print_date($lastControl->control_date, 'day');

            $sheet = new Sheet($db);

            $sheet->fetch($lastControl->fk_sheet);

            $out['LastControl']['sheet_title']  = $langs->transnoentities(dol_ucfirst($sheet->element));
            $out['LastControl']['sheet_ref']    = img_picto('', $sheet->picto, 'class="pictofixedwidth"') . $sheet->ref;
        }
    }

    if (!empty($lastControl->next_control_date)) {
        $nextControl                                   = floor(($lastControl->next_control_date - dol_now('tzuser'))/(3600 * 24));
        $out['nextControl']['title']                   = $langs->transnoentities('NextControl');
        $out['nextControl']['next_control_date']       = '<i class="objet-icon far fa-calendar"></i>' . dol_print_date($lastControl->next_control_date, 'day');
        $out['nextControl']['next_control_date_color'] = $lastControl->getNextControlDateColor();
        $out['nextControl']['next_control']            = '<i class="objet-icon far fa-clock"></i>' . $langs->transnoentities('In') . ' ' . $nextControl . ' ' . $langs->transnoentities('Days');
        if (getDolGlobalInt('DIGIQUALI_SHOW_ADD_CONTROL_BUTTON_ON_PUBLIC_INTERFACE') == 1) {
            $out['nextControl']['create_button'] = '<a href="' . dol_buildpath('custom/digiquali/view/control/control_card.php?action=create', 1). '" target="_blank"><div class="wpeo-button button-square-60 button-radius-1 button-primary"><i class="fas fa-plus"></i></div></a>';
        }
    }

    return $out;
}
