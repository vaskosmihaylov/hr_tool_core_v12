var navItems = document.querySelectorAll('.custom-nav__item a');
var url = window.location.pathname;
for(var i = 0; i < navItems.length; i++) {
    var path = navItems[i].getAttribute('href').split("/")[3];
    var name = navItems[i].getAttribute('href').split("/")[4];
    var pathName = "/" + path + '/' + name
    if(url.indexOf(pathName) > -1) {
        navItems[i].parentElement.classList.add('selected');
    }
}