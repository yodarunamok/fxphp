/**
 * Created with IntelliJ IDEA.
 * User: Chris Hansen (chris@iviking.org)
 * Date: 6/12/15
 * Time: 2:50 PM
 */
var sections = [
    {label: 'Welcome', address: 'index.html'},
    {label: 'FX Parser', address: 'fxparser.html'},
    {label: 'Functions', address: 'functions.html'},
    {label: 'Resources', address: 'resources.html'}
];

function initializePage () {
    var navigationList = $('#nav_list');
    var currentFile = location.pathname.substr(location.pathname.lastIndexOf('/') + 1);
    var rgbValues = [0, 0, 0];
    navigationList.fill();
    for (i = 0; i < sections.length; ++i) {
        if (currentFile == sections[i].address) navigationList.add(EE('li', sections[i].label));
        else {
            tempLink = EE('a', {'@href':sections[i].address}, sections[i].label);
            navigationList.add(EE('li', tempLink));
        }
    }
    for (i = 0; i < currentFile.length; ++i) {
        rgbValues[i % 3] += currentFile.charCodeAt(i);
    }
    // convert the link label to a color (stick with web safe colors as there is less chance of a weird one...)
    rgbValues[0] = ('0' + Number(rgbValues[0] % 5 * 51).toString(16)).substr(-2);
    rgbValues[1] = ('0' + Number(rgbValues[1] % 5 * 51).toString(16)).substr(-2);
    rgbValues[2] = ('0' + Number(rgbValues[2] % 5 * 51).toString(16)).substr(-2);
    rgb = rgbValues[0] + rgbValues[1] + rgbValues[2];
    backgroundStyle = 'background-image: -o-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: -moz-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: -webkit-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: linear-gradient(left, #' + rgb + ', white)';
    $('body').set('@style', backgroundStyle);
}