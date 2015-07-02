/**
 * Created with IntelliJ IDEA.
 * User: Chris Hansen (chris@iviking.org)
 * Date: 6/12/15
 * Time: 2:50 PM
 */
var sections = [
    {label: 'Welcome', address: 'index.html', children: null},
    {label: 'Getting Started', address: '', children: [
        {label: 'Introduction', address: 'introduction.html', children: null},
        {label: 'PHP Arrays', address: 'php_arrays.html', children: null},
        {label: 'Understanding FX', address: 'fx_intro.html', children: null},
        {label: 'FX Parser Setup', address: 'parser.html', children: null},
        {label: 'FX Parser Output', address: 'parser_out.html', children: null},
        {label: 'Final Comments', address: 'final_comments.html', children: null}
    ]},
    {label: 'Best Practices', address: 'best_practices.html', children: null},
    {label: 'Using image_proxy.php', address: 'image_proxy.html', children: null},
    {label: 'Functions', address: '', children: [
        {label: 'Query Setup', address: 'functions.html', children: [
            {label: 'FX()', address: 'functions.html#fx', children: null}
        ]},
        {label: 'Query Customization', address: 'functions_configure.html', children: [
            {label: 'AddDBParam()', address: 'functions_configure.html#AddDBParam', children: null},
            {label: 'AddDBParamArray()', address: 'functions_configure.html#AddDBParamArray', children: null},
            {label: 'AddSortParam()', address: 'functions_configure.html#AddSortParam', children: null},
            {label: 'FindQuery_AND()', address: 'functions_configure.html#FindQuery_AND', children: null},
            {label: 'FlattenInnerArray', address: 'functions_configure.html#FlattenInnerArray', children: null},
            {label: 'FMPostQuery()', address: 'functions_configure.html#FMPostQuery', children: null},
            {label: 'FMSkipRecords()', address: 'functions_configure.html#FMSkipRecords', children: null},
            {label: 'FMUseCURL()', address: 'functions_configure.html#FMUseCURL', children: null},
            {label: 'PerformFMScript()', address: 'functions_configure.html#PerformFMScript', children: null},
            {label: 'PerformFMScriptPrefind()', address: 'functions_configure.html#PerformFMScript', children: null},
            {label: 'PerformFMScriptPresort()', address: 'functions_configure.html#PerformFMScript', children: null},
            {label: 'SetCharacterEncoding()', address: 'functions_configure.html#SetCharacterEncoding', children: null},
            {label: 'SetDataKey()', address: 'functions_configure.html#SetDataKey', children: null},
            {label: 'SetDataParamsEncoding()', address: 'functions_configure.html#SetDataParamsEncoding', children: null},
            {label: 'SetDBData()', address: 'functions_configure.html#SetDBData', children: null},
            {label: 'SetDBPassword()', address: 'functions_configure.html#SetDBPassword', children: null},
            {label: 'SetDBUserPass()', address: 'functions_configure.html#SetDBUserPass', children: null},
            {label: 'SetDefaultOperator()', address: 'functions_configure.html#SetDefaultOperator', children: null},
            {label: 'SetFMGlobal()', address: 'functions_configure.html#SetFMGlobal', children: null},
            {label: 'SetLogicalOR()', address: 'functions_configure.html#SetLogicalOR', children: null},
            {label: 'SetModID()', address: 'functions_configure.html#SetModID', children: null},
            {label: 'SetPortalRow()', address: 'functions_configure.html#SetPortalRow', children: null},
            {label: 'SetRecordID()', address: 'functions_configure.html#SetRecordID', children: null},
            {label: 'SetSkipSize()', address: 'functions_configure.html#SetSkipSize', children: null},
            {label: 'SQLFuzzyKeyLogicOn()', address: 'functions_configure.html#SQLFuzzyKeyLogicOn', children: null}
        ]},
        {label: 'Query Execution', address: 'functions_execute.html', children: null}
    ]},
    {label: 'Resources', address: 'resources.html', children: null}
];

var currentFile = location.pathname.substr(location.pathname.lastIndexOf('/') + 1);

function initializePage () {
    var navigationList = $('#left_navigation');
    var rgbValues = [0, 0, 0];
    navigationList.fill();
    populateNavigation(navigationList, sections);
    // convert the link label to a color (stick with web safe colors as there is less chance of a weird one...)
    for (i = 0; i < currentFile.length; ++i) {
        rgbValues[i % 3] += currentFile.charCodeAt(i);
    }
    rgbValues[0] = ('0' + Number(rgbValues[0] % 5 * 51).toString(16)).substr(-2);
    rgbValues[1] = ('0' + Number(rgbValues[1] % 5 * 51).toString(16)).substr(-2);
    rgbValues[2] = ('0' + Number(rgbValues[2] % 5 * 51).toString(16)).substr(-2);
    rgb = rgbValues[0] + rgbValues[1] + rgbValues[2];
    // now take that color, and use it as a gradient background for the page
    backgroundStyle = 'background-image: -o-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: -moz-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: -webkit-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: linear-gradient(left, #' + rgb + ', white)';
    $('body').set('@style', backgroundStyle);
}

function populateNavigation (navElement, navArray) {
    for (var i = 0; i < navArray.length; ++i) {
        var currentElement = navArray[i];
        var currentNav = null;
        var currentSubnav = null;
        // figure out how the navigation element should look
        if (currentFile == currentElement.address) currentNav = EE('li', {'@class':'active'}, currentElement.label);
        else if (currentElement.address == '') currentNav = EE('li', currentElement.label);
        else {
            currentNav = EE('li', EE('a', {'@href':currentElement.address, '@onclick':'openFunction(this.hash);'}, currentElement.label));
        }
        // does the current element have children? handle them
        if (currentElement.children !== null) {
            currentSubnav = EE('ul', {'@class':'nav_list'});
            populateNavigation(currentSubnav, currentElement.children);
            currentNav.add([EE('br'), currentSubnav]);
        }
        // add the current navigation element to the list
        navElement.add(currentNav);
    }
}

function openFunction (linkHash) {
    var headId = linkHash + '_';
    var pseudoClick = new MouseEvent("click");
    $(headId)[0].dispatchEvent(pseudoClick);

}

$('.function_name').on('click', function() {
    $('#' + this[0].id + 'body').toggle('inactive', 'active');
});