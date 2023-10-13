# Custom Inputfield Attributes for ProcessWire

This module enhances both the interactive and API configurability of 
fields in ProcessWire (both admin and front-end). It can add custom 
attributes to Inputfields in ProcessWire, FormBuilder, or other 
Inputfield forms. Custom attributes can be configured interactively in the
admin, or they can be added via this module’s API method. 

Custom attributes can be added to the `<input>` element of an Inputfield or 
they can be added to the wrapping `.Inputfield` element. You can add any
attribute that you want (with a few exceptions), whether replacing or appending 
existing attributes, adding `data-*` attributes or any other named attribute.

This feature was originally considered for the core but I'm not sure enough
people need this ability to warrant that, so have developed this module instead.
This module might also be useful to other module developers looking for an 
example of how to modify or add Inputfield attributes. If so, feel free to 
copy or adapt any code out of this into your own.


## Install

*ProcessWire 3.0.220 or newer is recommended.*

1. Copy the files for this module to `/site/modules/CustomInputfieldAttributes/`
2. In your admin go to Modules > Refresh. 
3. Click “Install” for Modules > Site > Custom > Custom Inputfield Attributes.
4. Note the settings on the configuration screen and adjust as needed.


## Usage

- **ProcessWire fields**  
  When editing a field in ProcessWire, you'll see the “Add Custom Attributes” 
  setting on the “Input” tab, towards the bottom. 
 

- **FormBuilder**  
  When editing a field in FormBuilder, you'll see the “Add Custom Attributes”
  setting on the “Details” tab, towards the bottom.
 

- **Elsewhere**  
  When editing other forms in ProcessWire (such as in LoginRegisterPro) you'll
  see the “Add Custom Attributes” setting near other input settings such as 
  visibility and required settings. 


## Inherent limitations

Custom attributes may not work on all Inputfield types, as not all Inputfield
types render attribute strings dynamically, even if most do. For this reason
the module lets you optionally specify which Inputfield types you want to
enable it for in the module configuration. Note however that the wrap option
that applies to the wrapping `.Inputfield` element should work on most if not
all Inputfields, since that portion is handled by the same code in the core
for all Inputfields. 

There are also cases where an Inputfield doesn't have a specific `<input>`
or `<select>` element where attributes would apply, such as repeatable types.
For these types, this module is not likely to be able to do anything, or 
it may produce unexpected results. 

## Built-in limitations

This module won't let you add certain attributes interactively, such as 
`id`, `name`, or any `on*` attributes like `onchange`, `onfocus`, 
`onblur`, etc. 

Should you need them, it *will* let you add those attributes using the 
module’s API, or you can add/modify/remove the limits directly from the 
module file using the `$badNames` and `$badValues` array settings near 
the top of the module file. 

*It may be that there are more attributes it should block that I'm not
yet aware of. Please let me know if you have any suggestions.*


## Append attributes

By default, this module will append `class` and `style` attributes rather
than replace them. It takes care to also append a space before any added
class names, or a semicolon `;` before any added styles. If needed, you 
can add more append attributes from the module's configuration. Other
append attributes will be treated like class attributes, appending a
space before the added values. 


## API

This module will let you add custom attributes from an API method that
is likely to be called from your /site/ready.php file:
~~~~~
// get the module
$cia = $modules->get('CustomInputfieldAttributes');

// add a class to <input> element for Inputfield named "title"
$cia->addAttribute('title', 'class', 'uk-form-large'); 

// add a class to wrapping .Inputfield element for "headline"
$cia->addAttribute('title', 'class', 'InputfieldIsPrimary'); 

// add an onchange event attribute to "headline"
$cia->addAttribute('headline', 'onchange', 'alert("Headline changed")');
~~~~~
This will add the attribute to any Inputfield having the given name, whether
it appears in a ProcessWire field, FormBuilder, or some other form in the
admin or front-end. So it's possible you may need to be more selective 
before adding an attribute. For instance:
~~~~~
if($page->process == 'ProcessPageEdit') {
  $cia->addAttribute('title', 'class', 'uk-form-large'); 
}
if($page->template->name === 'contact') {
  $cia->addAttribute('phone', 'placeholder', 'i.e. 123-123-1234'); 
}
~~~~~