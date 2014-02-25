/**
 * Binds a TinyMCE widget to <textarea> elements.
 */
angular.module('ui.myTinymce', [])
  .value('uiTinymceConfig', {})
  .directive('uiMyTinymce', ['uiTinymceConfig', function (uiTinymceConfig) {
    
    var defaults = {
    	plugins: "textcolor autolink link image anchor filemanager code table",
    	toolbar: "undo link image | styleselect fontsizeselect forecolor | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent",
  		external_filemanager_path:"filemanager/",
   		filemanager_title:"Responsive Filemanager"
    };
    
    if (!uiTinymceConfig || !(_.keys(uiTinymceConfig).length))
    {
    	uiTinymceConfig = defaults;
	}
    
    uiTinymceConfig = uiTinymceConfig || defaults;
    var generatedIds = 0;
    return {
      require: 'ngModel',
      link: function (scope, elm, attrs, ngModel) {
        var expression, options, tinyInstance,
          updateView = function () {
            ngModel.$setViewValue(elm.val());
            if (!scope.$$phase) {
              scope.$apply();
            }
          };
        // generate an ID if not present
        if (!attrs.id) {
          attrs.$set('id', 'uiTinymce' + generatedIds++);
        }

        if (attrs.uiMyTinymce) {
          expression = scope.$eval(attrs.uiMyTinymce);
        } else {
          expression = {};
        }
        options = {
          // Update model when calling setContent (such as from the source editor popup)
          setup: function (ed) {
            var args;
            ed.on('init', function(args) {
              ngModel.$render();
            });
            // Update model on button click
            ed.on('ExecCommand', function (e) {
              ed.save();
              updateView();
            });
            // Update model on keypress
            ed.on('KeyUp', function (e) {
              ed.save();
              updateView();
            });
            // Update model on change, i.e. copy/pasted text, plugins altering content
            ed.on('SetContent', function (e) {
              if(!e.initial){
                ed.save();
                updateView();
              }
            });
            if (expression && expression.setup) {
              scope.$eval(expression.setup);
              delete expression.setup;
            }
          },
          mode: 'exact',
          elements: attrs.id
        };
        // extend options with initial uiTinymceConfig and options from directive attribute value
        angular.extend(options, uiTinymceConfig, expression);
        setTimeout(function () {
          tinymce.init(options);
        });

        /* todo: possibly remove after Angular 1.2.0-rc3 */
        var stopWatch = scope.$watch(attrs.ngModel, function(newValue, oldValue)
        {
          if (!tinyInstance) {
            tinyInstance = tinymce.get(attrs.id);
          }
        if (tinyInstance) {
        	tinyInstance.setContent(newValue);
        	stopWatch();
        }
		});
        
        ngModel.$render = function() {
          if (!tinyInstance) {
            tinyInstance = tinymce.get(attrs.id);
          }
          if (tinyInstance) {
            tinyInstance.setContent(ngModel.$viewValue || '');
          }
        };
      }
    };
  }]);
