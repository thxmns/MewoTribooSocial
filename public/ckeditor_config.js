
 CKEDITOR.editorConfig = function( config ) {

	config.contentsCss = ['/css/custom-ckeditor.css'];

	config.toolbarGroups = [
		{ name: 'document', groups: [ 'mode', 'document', 'doctools','source' ] },
		{ name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
		{ name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing' ] },
		{ name: 'forms', groups: [ 'forms' ] },
		'/',
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'links', groups: [ 'links' ] },
		{ name: 'insert', groups: [ 'insert' ] },
		'/',
		{ name: 'styles', groups: [ 'styles' ] },
		{ name: 'colors', groups: [ 'colors' ] },
		{ name: 'tools', groups: [ 'tools' ] },
		{ name: 'about', groups: [ 'about' ] }
	];

	config.removeButtons = 'SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Save,Templates,NewPage,ExportPdf,Preview,Print,Find,Replace,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Image,Table,HorizontalRule,Smiley,SpecialChar,PageBreak,Iframe,Maximize,ShowBlocks,About,Cut,Undo,Redo,Copy,Paste,PasteText,PasteFromWord';
    config.colorButton_colors = '2B345F,B1B3B5,ffffff,000,106273,EB6502,00443A,CABDB0,';
    config.font_names =
		"Figtree, sans-serif;"
    config.stylesSet = [
	{ name: 'Title - H1', element: 'h1', styles: { 'font-weight': '700', 'font-size': '50px', 'line-height': '62px' }},
	{ name: 'Title - H2', element: 'h2', styles: { 'font-weight': '600', 'font-size': '20px', 'line-height': '24px' }},
    { name: 'Title - H3', element: 'h3', styles: { 'font-weight': '300', 'font-size': '20px', 'line-height': '30px' }},
	{ name: 'Title - H4', element: 'h4', styles: { 'font-weight': '300', 'font-size': '20px', 'line-height': '21px' }},
	{ name: 'Body-Text', element: 'p', styles: { 'font-weight': '300', 'font-size': '15px', 'line-height': '30px' }},
	{ name: 'Quote-Text', element: 'p', styles: { 'font-weight': '300', 'font-size': '18px', 'line-height': '30px' }},

    ];
	config.removePlugins = 'format';
	config.plugins = config.plugins.replace(/(.*\s|^)widget(.*\s|$)/, '');



	
};