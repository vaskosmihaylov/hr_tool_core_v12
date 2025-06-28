var Validator = function (customMessages) {
    var self = this;
    var forms = document.getElementsByTagName('form');
    var requiredFields = document.querySelectorAll('[required]');
    var messages = {
        valueMissing: 'Моля попълнете това поле!',
        noMatch: 'Стойността на двете полета не съвпадат!',
        badInput: 'Невалидни данни!',
        patternMismatch: 'Полето не отговаря на изискванията: $title',
        rangeOverflow: 'Максималната стойност е: $max',
        rangeUnderflow: 'Минималната стойност е: $min',
        stepMismatch: 'Позволен размер на стъпка: $step',
        tooLong: 'Максимален брой символи: $maxlength . По настоящем използвате $currentlength',
        tooShort: 'Минимален брой символи: $minlength . По настоящем използвате $currentlength',
        typeMismatch: 'Въведените данни не отговарят на поле от тип: $type',
    }
    if (customMessages) {
        for (var attrname in customMessages) {
            messages[attrname] = customMessages[attrname];
        }
    }

    this.init = function() {
        forms = document.getElementsByTagName('form');
        requiredFields = document.querySelectorAll('[required]');
        return this;
    }
    
    this.validate = function () {
        forms = document.getElementsByTagName('form');
        for (var i = 0; i < forms.length; i++) {
            forms[i].setAttribute('novalidate', 'true');
            forms[i].addEventListener('submit', function (e) {
                
                if (!self.validateForm(this)) {
                    e.preventDefault();
                }
            });
        }
        for (var i = 0; i < requiredFields.length; i++) {
            requiredFields[i].addEventListener('input', function(){
                self.validateElement(this);
            });
            requiredFields[i].addEventListener('change', function(){
                self.validateElement(this);
            });
        }
    }

    this.isValid = function (element) {
        element.setCustomValidity('');
        var regex = new RegExp(element.getAttribute('pattern') || '^.+$');
        if (element.validity.valueMissing || element.value.length == 0) {
            element.setCustomValidity(element.getAttribute('data-msg-required') || messages.valueMissing);
            return false;
        }
        if (
            element.hasAttribute('data-match') && document.getElementById(element.getAttribute("data-match"))) {
            var matchId = element.getAttribute("data-match");
            var value = element.value;
            var confirmValue = document.getElementById(matchId).value;
            if (value != confirmValue) {
                element.setCustomValidity(messages.noMatch.replace('$matchElement', matchId));
                return false;
            }
        }

        if (element.validity.badInput) {
            element.setCustomValidity(messages.badInput);
            return false;
        }
        if (element.validity.rangeOverflow) {
            element.setCustomValidity(messages.rangeOverflow.replace('$max', element.getAttribute('max')));
            return false;
        }
        if (element.validity.rangeUnderflow) {
            element.setCustomValidity(messages.rangeUnderflow.replace('$min', element.getAttribute('min')));
            return false;
        }
        if (element.validity.stepMismatch) {
            element.setCustomValidity(messages.stepMismatch.replace('$step', element.getAttribute('step')));
            return false;
        }
        if (element.validity.tooLong || element.value.length > parseInt(element.getAttribute('maxlength'))) {
            console.log('aniii');
            element.setCustomValidity(messages.tooLong.replace('$maxlength', element.getAttribute('maxlength')).replace('$currentlength', element.value.length));
            return false;
        }
        if (element.validity.tooShort || (element.value.length < parseInt(element.getAttribute('minlength')))) {
            element.setCustomValidity(messages.tooShort.replace('$minlength', element.getAttribute('minlength')).replace('$currentlength', element.value.length));
            return false;
        }
        if (element.validity.patternMismatch || !regex.test(element.value)) {
            element.setCustomValidity(messages.patternMismatch.replace('$title', element.getAttribute('title')));
            return false;
        }
        if (element.validity.typeMismatch) {
            element.setCustomValidity(messages.typeMismatch.replace('$type', element.getAttribute('type')));
            return false;
        }
        return true;
    };

    this.showError = function (element, settings) {
        var options = {
            text: element.validationMessage,
            class: 'error'
        }
        if (settings) {
            for (var option in settings) {
                options[option] = settings[option];
            }
        }
        if (
            element.nextElementSibling &&
            element.nextElementSibling.hasAttribute('for') &&
            element.nextElementSibling.getAttribute('for') == element.getAttribute('id')
        ) {
            element.nextElementSibling.innerText = options.text;
        } else {
            var label = document.createElement('label');
            label.setAttribute('for', element.getAttribute('id'))
            label.setAttribute('class', options.class);
            label.innerText = options.text;
            element.parentNode.insertBefore(label, element.nextElementSibling);
        }

    };

    this.hideError = function (element) {
        if (
            element.nextElementSibling &&
            element.nextElementSibling.hasAttribute('for') &&
            element.nextElementSibling.getAttribute('for') == element.getAttribute('id')) {
            element.parentNode.removeChild(element.nextElementSibling)
        }
    };

    this.validateElement = function (element) {
        if (!self.isValid(element)) {
            element.classList.add('error');
            self.showError(element, {class: 'text-danger'});
            return false;
        } else if (self.isValid(element) && element.classList.contains('error')) {
            element.classList.remove('error');
            self.hideError(element);
            return true;
        } else if (self.isValid(element)) {
            self.hideError(element);
            return true;
        }
    };

    this.validateParentForm = function (event) {
        node = this;
        while (node.nodeName != "FORM" && node.parentNode) {
            node = node.parentNode;
        }
        self.validateForm(node);
    };

    this.validateForm = function (form) {
        var errors = 0;
        requiredFields = form.querySelectorAll('[required]'); 
        for (var i = 0; i < requiredFields.length; i++) {
            var element = requiredFields[i];
            if(self.validateElement(element) !== true){
                errors = errors + 1;
            };
        }
        return errors ? false : true;
    }
}

var maxlengthFields = document.querySelectorAll('[maxlength]');
for(var i = 0; i < maxlengthFields.length; i++) {
	maxlengthFields[i].addEventListener('input', function(e) {
		var maxlength = parseInt(this.getAttribute('maxlength'));
		var percent = Math.ceil(this.value.length * 100 / maxlength);
		this.style.backgroundSize = percent + '% 3px';
		// console.log(percent);
	});
}


// USAGE:
 var validator = new Validator();
 validator.validate();
