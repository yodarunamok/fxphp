<?php
print_r( $_REQUEST );
foreach( $_REQUEST['vl'] as $key => $value ) {
    if( $value != 'Disabled' ) {
        $activeFields[] = $key;
    }
}

foreach( $activeFields as $value ) {
    $newForm[$value]['type'] = $_REQUEST['vl'][$value];
    $newForm[$value]['class'] = $_REQUEST['cl'][$value];
    $newForm[$value]['valueList'] = $_REQUEST['vv'][$value];
    $newForm[$value]['cmpVL2FieldValue'] = $_REQUEST['vlNoYes'][$value];
}

//print_r( $newForm );

foreach( $newForm as $key => $value ) {
    switch( $value['type'] ) {
        case 'Image':
        case 'Image text as reference':
            $row[] = '<td>'.$key.'</td><td><img src="'.$key.'"></td>';
        break;
        case 'TEXTAREA':
            $row[] = '<td>'.$key.'</td><td><textarea name="'.$key.'">'.$value.'</textarea></td>';
        break;
        case 'Radio':
            $row[] = '<td>'.$key.'</td><td><input type="radio" name="'.$key.'" value=""></td>';
        break;
        case 'Checkbox':
            $row[] = '<td>'.$key.'</td><td><input type="checkbox" name="'.$key.'" value=""></td>';
        break;
        case 'Select':
            $row[] = '<td>'.$key.'</td><td><select name="'.$key.'"></select></td>';
        break;
        case 'Plain output':
            $row[] = '<td>'.$key.'</td><td>'.$value.'</td>';
        break;
        default:
            $row[] = '<td>'.$key.'</td><td><input type="'.$value['type'].'" name="'.$key.'" value=""></td>';
        break;
    }
}
$output = '<table><tr>'
    . implode( "</tr>\n<tr>", $row )
    . '</tr></table>';
echo $output;
echo '<textarea>'.$output.'</textarea>';

?>

