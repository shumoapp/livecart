function validateForm(form)
{
	Element.saveTinyMceFields(form);
    ActiveForm.prototype.resetErrorMessages(form);

    var validatorData = form._validator.value;
	var validator = validatorData.parseJSON();   
    var isFormValid = true;
    var focus = true;

	$H(validator).each(function(field)
	{
		if (!form[field.key]) throw $continue;
        
		var formElement = form[field.key];
        $H(field.value).each(function(func) 
		{                
			if (window[func.key] && !window[func.key](formElement, func.value.param)) // If element is not valid
			{
                ActiveForm.prototype.setErrorMessage(formElement, func.value.error, focus);
				isFormValid = false;
                focus = false;
			}
	    });
	});
    
	return isFormValid;
}

function applyFilters(form, ev)
{	
    if(!ev || !ev.target) 
    { 
        ev = window.event; 
        ev.target = ev.srcElement;
    }

	var filterData = form.elements.namedItem('_filter').value;
	var filter = filterData.parseJSON();

	element = ev.target;	
	elementFilters = filter[element.id];
	
	if ('undefined' == 'elementFilters')
	{
	  	return false;
	}

	for (k in elementFilters)
	{
		if(typeof elementFilters[k] == 'object')
		{
		  	eval(k + '(element, elementFilters[k]);');
		}
	}	
}

/*********************************************
	Checks (validators)
*********************************************/
function trim(strValue)
{
 	var objRegExp = /^(\s*)$/;
    //check for all spaces
    if(objRegExp.test(strValue))
    {
		strValue = strValue.replace(objRegExp, '');
       	if( strValue.length == 0)
       	{
        	return strValue;
       	}
    }
   	//check for leading & trailing spaces
   	objRegExp = /^(\s*)([\W\w]*)(\b\s*$)/;
   	if(objRegExp.test(strValue))
   	{
       //remove leading and trailing whitespace characters
       strValue = strValue.replace(objRegExp, '$2');
    }
  	return strValue;
}


function IsNotEmptyCheck(element, params)
{
	if (element.getAttribute("type") == "checkbox") {
		return element.checked;
	}
	return (element.value.length > 0);
}

function MinLengthCheck(element, params)
{
	return (element.value.length >= params.minLength);
}

function PasswordEqualityCheck(element, params)
{
    for(i in element.form.elements)
    {
        if(element.form.elements[i].type == 'password')
        {
            return (element.value == element.form.elements[i].value);
        }
    }
	
    return true;
}

function MaxLengthCheck(element, params)
{
	return (element.value.length <= params.maxLength);
}

function IsValidEmailCheck(element, params)
{
	re = new RegExp(/^[a-zA-Z0-9][a-zA-Z0-9\._\-]+@[a-zA-Z0-9_\-][a-zA-Z0-9\._\-]+\.[a-zA-Z]{2,}$/);
	return (re.exec(element.value));
}

function IsValueInSetCheck(element, params)
{

}

function IsNumericCheck(element, constraint)
{
  	if (element.value == '')
  	{
  		return true;
  	}
	re = new RegExp(/(^-?\d+\.\d+$)|(^-?\d+$)|(^-?\.\d+$)/);
	return(re.exec(element.value));
}

function IsIntegerCheck(element, constraint)
{
  	if (constraint.letEmptyString && element.value == '')
  	{
  		return true;
  	}
	re = new RegExp(/^-?\d+$/);
	return(re.exec(element.value));
}

function MinValueCheck(element, constraint)
{
  	return element.value >= constraint.minValue || element.value == '';
}

function MaxValueCheck(element, constraint)
{
  	return element.value <= constraint.maxValue || element.value == '';
}

/*********************************************
	Filters
*********************************************/
function NumericFilter(element, params)
{
	var value = element.value;
	value = value.replace(',' , '.');
	
	// only keep the last comma
	parts = value.split('.');

	value = '';
	for (k = 0; k < parts.length; k++)
	{
		value += parts[k] + ((k == (parts.length - 2)) && (parts.length > 1) ? '.' : '');
	}

	// split digits and decimal part
	parts = value.split('.');
	
	// leading comma (for example: .5 converted to 0.5)
	if ('' == parts[0] && 2 == parts.length)
	{
	  	parts[0] = '0';
	}
	
	//next remove all characters save 0 though 9
	//in both elements of the array
	dollars = parts[0].replace(/^-?[^0-9]-/gi, '');

	if ('' != dollars && '-' != dollars)
	{
        dollars = parseInt(dollars);	  

        if(!dollars) dollars = 0;
	}
	
	if (2 == parts.length)
	{
		cents = parts[1].replace(/[^0-9]/gi, '');
		dollars += '.' + cents;
	}
	
	element.value = dollars;
}

function RegexFilter(element, params)
{
	var regex = new RegExp(params['regex'], 'gi');
	element.value = element.value.replace(regex, '');
}

/*
 * JSON parser
 */
(function () {
    var m = {
            '\b': '\\b',
            '\t': '\\t',
            '\n': '\\n',
            '\f': '\\f',
            '\r': '\\r',
            '"' : '\\"',
            '\\': '\\\\'
        },
        s = {
            array: function (x) {
                var a = ['['], b, f, i, l = x.length, v;
                for (i = 0; i < l; i += 1) {
                    v = x[i];
                    f = s[typeof v];
                    if (f) {
                        v = f(v);
                        if (typeof v == 'string') {
                            if (b) {
                                a[a.length] = ',';
                            }
                            a[a.length] = v;
                            b = true;
                        }
                    }
                }
                a[a.length] = ']';
                return a.join('');
            },
            'boolean': function (x) {
                return String(x);
            },
            'null': function (x) {
                return "null";
            },
            number: function (x) {
                return isFinite(x) ? String(x) : 'null';
            },
            object: function (x) {
                if (x) {
                    if (x instanceof Array) {
                        return s.array(x);
                    }
                    var a = ['{'], b, f, i, v;
                    for (i in x) {
                        v = x[i];
                        f = s[typeof v];
                        if (f) {
                            v = f(v);
                            if (typeof v == 'string') {
                                if (b) {
                                    a[a.length] = ',';
                                }
                                a.push(s.string(i), ':', v);
                                b = true;
                            }
                        }
                    }
                    a[a.length] = '}';
                    return a.join('');
                }
                return 'null';
            },
            string: function (x) {
                if (/["\\\x00-\x1f]/.test(x)) {
                    x = x.replace(/([\x00-\x1f\\"])/g, function(a, b) {
                        var c = m[b];
                        if (c) {
                            return c;
                        }
                        c = b.charCodeAt();
                        return '\\u00' +
                            Math.floor(c / 16).toString(16) +
                            (c % 16).toString(16);
                    });
                }
                return '"' + x + '"';
            }
        };

    Object.prototype.toJSONString = function () {
        return s.object(this);
    };

    Array.prototype.toJSONString = function () {
        return s.array(this);
    };
})();

String.prototype.parseJSON = function () {
    try {
        return !(/[^,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/.test(
                this.replace(/"(\\.|[^"\\])*"/g, ''))) &&
            eval('(' + this + ')');
    } catch (e) {
        return false;
    }
};