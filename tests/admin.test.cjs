const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');
function builder() {
    const dom = new JSDOM('<form id="post"><input id="alify-schema" value="[]"><div id="alify-fields"></div><div id="alify-empty"></div><span id="alify-count"></span><button type="button" id="alify-add"></button></form>', {runScripts: 'outside-only'});
    const w = dom.window;
    w.AlifyCF = {types: {text: 'Text', wysiwyg: 'WYSIWYG Editor', link: 'Link', color: 'Color Picker', time: 'Time', image: 'Image', file: 'File', checkbox: 'Checkbox', select: 'Select', repeater: 'Repeater', gallery: 'Gallery', group: 'Group', flexible: 'Flexible Content'}};
    w.jQuery = () => ({sortable() {}});
    w.confirm = () => true;
    w.alert = message => { w.lastAlert = message; };
    w.eval(fs.readFileSync('assets/builder.js', 'utf8'));
    return w;
}
function set(w, prop, value) { const el = w.document.querySelector(`[data-prop="${prop}"]`); el.value = value; el.dispatchEvent(new w.Event('input', {bubbles: true})); }
const schema = w => JSON.parse(w.document.getElementById('alify-schema').value);
test('builder adds fields, generates names, duplicates, moves and removes', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Hero Image'); set(w, 'type', 'image');
    assert.equal(schema(w)[0].name, 'hero_image');
    assert.equal(schema(w)[0].type, 'image');
    d.querySelector('[data-action="duplicate"]').click();
    assert.equal(schema(w)[1].name, 'hero_image_copy');
    d.querySelectorAll('[data-action="up"]')[1].click();
    assert.equal(schema(w)[0].name, 'hero_image_copy');
    d.querySelector('[data-action="remove"]').click();
    assert.equal(schema(w).length, 1);
    w.close();
});
test('invalid and duplicate names prevent submission; corrected names submit', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Title'); set(w, 'name', 'bad-name');
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), false);
    assert.match(w.lastAlert, /unique valid/);
    set(w, 'name', 'title');
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), true);
    d.querySelector('[data-action="duplicate"]').click();
    d.querySelectorAll('[data-prop="name"]')[1].value = 'title';
    d.querySelectorAll('[data-prop="name"]')[1].dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), false);
    w.close();
});
test('labels are rendered as text, choices toggle correctly', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', '<img src=x onerror=alert(1)>');
    d.querySelector('[data-action="duplicate"]').click();
    assert.equal(d.querySelectorAll('img').length, 0);
    set(w, 'type', 'select'); assert.equal(d.querySelector('.alify-choices').hidden, false);
    set(w, 'type', 'image'); assert.equal(d.querySelector('.alify-choices').hidden, true);
    w.close();
});
function media(type = 'image', attachment = {id: 50, type: 'image', filename: 'photo.jpg', sizes: {thumbnail: {url: 'https://example.test/image.jpg'}}}) {
    const dom = new JSDOM(`<div class="alify-media" data-type="${type}"><input type="hidden"><div class="alify-media-preview"><img hidden><span></span></div><button data-media="choose">Choose</button><button data-media="remove" hidden>Remove</button></div>`, {runScripts: 'outside-only', url: 'https://example.test/wp-admin/post.php'});
    const w = dom.window;
    w.wp = {media: options => {
        w.options = options;
        return {on(event, callback) { this.callback = callback; }, state() { return {get() { return {first() { return {toJSON() {return attachment;}}; }}; }}; }, open() { this.callback(); }};
    }};
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    return w;
}
test('image picker selects an attachment, previews thumbnail and clears it', () => {
    const w = media(), d = w.document;
    d.querySelector('[data-media="choose"]').click();
    assert.equal(w.options.library.type, 'image');
    assert.equal(d.querySelector('input').value, '50');
    assert.equal(d.querySelector('img').src, 'https://example.test/image.jpg');
    d.querySelector('[data-media="remove"]').click();
    assert.equal(d.querySelector('input').value, '');
    assert.equal(d.querySelector('img').hidden, true);
    assert.equal(d.querySelector('img').hasAttribute('src'), false);
    w.close();
});
test('file picker allows documents and renders filenames as text', () => {
    const w = media('file', {id: 51, type: 'application', filename: '<script>bad()</script>'}), d = w.document;
    d.querySelector('[data-media="choose"]').click();
    assert.equal(d.querySelector('input').value, '51');
    assert.equal(d.querySelector('img').hidden, true);
    assert.equal(d.querySelectorAll('script').length, 0);
    assert.equal(d.querySelector('span').textContent, '<script>bad()</script>');
    w.close();
});
test('image picker rejects non-images and unsafe preview URLs', () => {
    const w = media('image', {id: 51, type: 'application'});
    w.document.querySelector('[data-media="choose"]').click();
    assert.equal(w.document.querySelector('input').value, ''); w.close();
    const x = media('image', {id: 50, type: 'image', url: 'javascript:alert(1)'});
    x.document.querySelector('[data-media="choose"]').click();
    assert.equal(x.document.querySelector('img').hasAttribute('src'), false); x.close();
});

test('builder persists conditional rules and rejects removed source references', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Source');
    const sourceKey = schema(w)[0].key;
    d.getElementById('alify-add').click();
    const cards = () => d.querySelectorAll('.alify-card');
    const label = cards()[1].querySelector('[data-prop="label"]'); label.value = 'Target'; label.dispatchEvent(new w.Event('input', {bubbles: true}));
    cards()[1].querySelector('[data-condition-prop="enabled"]').click();
    function change(prop, value) { const el = cards()[1].querySelector(`[data-condition-prop="${prop}"]`); el.value = value; el.dispatchEvent(new w.Event('input', {bubbles: true})); }
    change('field', sourceKey); change('value', 'yes');
    assert.equal(schema(w)[1].condition.field, sourceKey);
    assert.equal(schema(w)[1].condition.value, 'yes');
    cards()[1].querySelector('[data-action="duplicate"]').click();
    change('value', 'no');
    assert.equal(schema(w)[2].condition.value, 'yes', 'Duplicate must not share mutable rule object');
    cards()[0].querySelector('[data-action="remove"]').click();
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), false);
    assert.match(w.lastAlert, /unconditional/);
    w.close();
});
function conditionalEditor(type, operator, comparison, sourceHTML) {
    const rule = JSON.stringify({field: 'source', operator, value: comparison}).replace(/"/g, '&quot;');
    const dom = new JSDOM(`<div class="alify-values"><div class="alify-value" data-field-key="source" data-field-type="${type}">${sourceHTML}</div><div class="alify-value" data-field-key="target" data-field-type="text" data-condition="${rule}"><input required value="retained"></div></div>`, {runScripts: 'outside-only'});
    dom.window.eval(fs.readFileSync('assets/values.js', 'utf8'));
    return dom.window;
}
test('conditional editor hides/disables required fields, restores them, and retains input', () => {
    const w = conditionalEditor('true_false', 'equals', '1', '<input type="checkbox" value="1">'), d = w.document;
    const target = d.querySelector('[data-field-key="target"]');
    assert.equal(target.hidden, true); assert.equal(target.querySelector('input').disabled, true);
    d.querySelector('[type="checkbox"]').click();
    assert.equal(target.hidden, false); assert.equal(target.querySelector('input').disabled, false);
    assert.equal(target.querySelector('input').required, true);
    d.querySelector('[type="checkbox"]').click();
    assert.equal(target.querySelector('input').value, 'retained'); w.close();
});
test('conditional editor supports all operators, zero and checkbox membership', () => {
    for (const [operator, expectedHidden] of Object.entries({equals: false, not_equals: true, empty: true, not_empty: false})) {
        const w = conditionalEditor('number', operator, '0', '<input type="number" value="0">');
        assert.equal(w.document.querySelector('[data-field-key="target"]').hidden, expectedHidden, operator); w.close();
    }
    const w = conditionalEditor('checkbox', 'equals', 'a', '<input type="checkbox" value="a" checked><input type="checkbox" value="b">');
    assert.equal(w.document.querySelector('[data-field-key="target"]').hidden, false); w.close();
    const x = conditionalEditor('true_false', 'empty', '', '<input type="checkbox" value="1">');
    assert.equal(x.document.querySelector('[data-field-key="target"]').hidden, false); x.close();
});
test('conditions are scoped to their own group', () => {
    const w = conditionalEditor('text', 'equals', 'yes', '<input value="no">'), d = w.document;
    const other = d.createElement('div'); other.className = 'alify-values';
    other.innerHTML = '<div class="alify-value" data-field-key="source"><input value="yes"></div>';
    d.body.prepend(other);
    other.querySelector('input').dispatchEvent(new w.Event('change', {bubbles: true}));
    assert.equal(d.querySelector('[data-field-key="target"]').hidden, true); w.close();
});

test('repeater builder adds local subfields, validates names and deep-copies schema', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Team'); set(w, 'type', 'repeater');
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), false);
    d.querySelector('[data-sub-action="add"]').click();
    const label = d.querySelector('[data-sub-prop="label"]');
    label.value = 'Member Name'; label.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[0].sub_fields[0].name, 'member_name');
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), true);
    assert.equal(d.querySelector('[data-sub-prop="type"] option[value="repeater"]'), null);
    d.querySelector('[data-action="duplicate"]').click();
    const subName = d.querySelector('[data-sub-prop="name"]');
    subName.value = 'changed'; subName.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[1].sub_fields[0].name, 'member_name');
    d.querySelector('[data-sub-action="add"]').click();
    const second = d.querySelectorAll('[data-sub-prop="label"]')[1];
    second.value = 'Member Name'; second.dispatchEvent(new w.Event('input', {bubbles: true}));
    // Rename first back so both local names conflict.
    const first = d.querySelector('[data-sub-prop="name"]'); first.value = 'member_name'; first.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), false);
    w.close();
});
function repeaterEditor(conditional = false) {
    const rule = conditional ? ' data-condition="{&quot;field&quot;:&quot;toggle&quot;,&quot;operator&quot;:&quot;equals&quot;,&quot;value&quot;:&quot;1&quot;}"' : '';
    const dom = new JSDOM(`<div class="alify-values"><div class="alify-value" data-field-key="toggle" data-field-type="true_false"><input type="checkbox" value="1" checked></div><div class="alify-value" data-field-key="team" data-field-type="repeater"${rule}><div class="alify-repeater" data-limit="3"><div class="alify-rows"></div><template class="alify-row-template"><div class="alify-row"><div class="alify-row-bar"><strong class="alify-row-label">Row</strong><button type="button" data-row-action="up">Up</button><button type="button" data-row-action="down">Down</button><button type="button" data-row-action="remove">Remove</button></div><label for="team-__ROW__-name">Name</label><input required id="team-__ROW__-name" name="values[team][rows][__ROW__][name]" value="__ROW__"><input type="hidden" name="values[team][rows][__ROW__][__row_complete]" value="1"></div></template><input type="hidden" name="values[team][__complete]" value="1"><button type="button" data-row-action="add">Add</button><span class="alify-row-count"></span></div></div></div>`, {runScripts: 'outside-only'});
    const w = dom.window; w.confirm = () => true;
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    return w;
}
test('repeater rows add with unique IDs and defaults, reorder without data loss, and remove', () => {
    const w = repeaterEditor(), d = w.document;
    const add = d.querySelector('[data-row-action="add"]');
    add.click(); add.click();
    const rows = () => Array.from(d.querySelector('.alify-rows').children);
    const inputs = rows().map(row => row.querySelector('input'));
    assert.notEqual(inputs[0].id, inputs[1].id);
    assert.equal(inputs[0].value, '__ROW__', 'Template substitution must not mutate defaults');
    assert.equal(rows()[0].querySelector('label').htmlFor, inputs[0].id);
    inputs[0].value = 'A'; inputs[1].value = 'B';
    rows()[1].querySelector('[data-row-action="up"]').click();
    assert.deepEqual(rows().map(row => row.querySelector('input').value), ['B', 'A']);
    rows()[0].querySelector('[data-row-action="down"]').click();
    assert.deepEqual(rows().map(row => row.querySelector('input').value), ['A', 'B']);
    rows()[0].querySelector('[data-row-action="remove"]').click();
    assert.equal(rows().length, 1); assert.equal(rows()[0].querySelector('input').value, 'B');
    assert.match(d.querySelector('.alify-row-count').textContent, /1 \/ 3/);
    w.close();
});
test('repeater row cap and move boundaries survive conditional refresh', () => {
    const w = repeaterEditor(true), d = w.document;
    const add = d.querySelector('[data-row-action="add"]');
    add.click(); add.click(); add.click(); add.click();
    assert.equal(d.querySelectorAll('.alify-row').length, 3);
    assert.equal(add.disabled, true);
    d.querySelector('[data-field-key="toggle"] input').click();
    assert.equal(d.querySelector('[data-field-key="team"]').hidden, true);
    assert.equal(d.querySelector('.alify-row input').disabled, true);
    d.querySelector('[data-field-key="toggle"] input').click();
    assert.equal(d.querySelector('.alify-row input').disabled, false);
    assert.equal(add.disabled, true);
    assert.equal(d.querySelector('[data-row-action="up"]').disabled, true);
    d.querySelector('[data-row-action="remove"]').click();
    assert.equal(add.disabled, false); w.close();
});

function galleryMarkup(name = 'values[photos]', limit = 3) {
    return `<div class="alify-gallery" data-limit="${limit}"><div class="alify-gallery-items"></div><input type="hidden" class="alify-gallery-complete" name="${name}[__complete]" value="1"><div class="alify-gallery-footer"><button type="button" data-gallery="add">Add</button><button type="button" data-gallery="clear">Clear</button><span class="alify-gallery-count"></span></div><p class="alify-gallery-status"></p></div>`;
}
function mockGalleryMedia(w) {
    w.selection = [];
    w.wp = {media: options => {
        w.galleryOptions = options;
        return {on(event, callback) { this.callback = callback; }, state() { return {get() { return {toJSON() { return w.selection; }}; }}; }, open() { this.callback(); }};
    }};
}
function galleryEditor() {
    const condition = JSON.stringify({field: 'photos', operator: 'equals', value: '50'}).replace(/"/g, '&quot;');
    const dom = new JSDOM(`<div class="alify-values"><div class="alify-value" data-field-key="photos" data-field-type="gallery">${galleryMarkup()}</div><div class="alify-value" data-field-key="caption" data-condition="${condition}"><input required></div></div>`, {runScripts: 'outside-only', url: 'https://example.test/wp-admin/post.php'});
    const w = dom.window; w.confirm = () => true; mockGalleryMedia(w);
    w.eval(fs.readFileSync('assets/gallery.js', 'utf8'));
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    return w;
}
const galleryImage = id => ({id, type: 'image', filename: 'Image ' + id, url: 'https://example.test/' + id + '.png'});
test('gallery picker appends unique images, submits ordered IDs, moves, removes and clears', () => {
    const w = galleryEditor(), d = w.document;
    w.selection = [galleryImage(50), galleryImage(52), galleryImage(50)];
    d.querySelector('[data-gallery="add"]').click();
    const ids = () => Array.from(d.querySelectorAll('.alify-gallery-items input')).map(input => input.value);
    assert.equal(w.galleryOptions.multiple, true); assert.equal(w.galleryOptions.library.type, 'image');
    assert.deepEqual(ids(), ['50', '52']);
    assert.equal(d.querySelector('.alify-gallery-items input').name, 'values[photos][ids][]');
    assert.equal(d.querySelector('[data-field-key="caption"]').hidden, false, 'Gallery source condition uses ID membership, not marker value');
    d.querySelector('[data-gallery="down"]').click(); assert.deepEqual(ids(), ['52', '50']);
    d.querySelectorAll('[data-gallery="up"]')[1].click(); assert.deepEqual(ids(), ['50', '52']);
    d.querySelector('[data-gallery="remove"]').click(); assert.deepEqual(ids(), ['52']);
    assert.equal(d.querySelector('[data-field-key="caption"]').hidden, true);
    d.querySelector('[data-gallery="clear"]').click(); assert.deepEqual(ids(), []);
    assert.equal(d.querySelector('.alify-gallery-complete').value, '1'); w.close();
});
test('gallery rejects over-limit batches atomically and preserves button limits after refresh', () => {
    const w = galleryEditor(), d = w.document;
    w.selection = [galleryImage(50), galleryImage(52)]; d.querySelector('[data-gallery="add"]').click();
    w.selection = [galleryImage(54), galleryImage(55)]; d.querySelector('[data-gallery="add"]').click();
    assert.equal(d.querySelectorAll('.alify-gallery-item').length, 2);
    assert.match(d.querySelector('.alify-gallery-status').textContent, /not changed/);
    w.selection = [galleryImage(54)]; d.querySelector('[data-gallery="add"]').click();
    d.querySelector('[data-field-key="caption"] input').dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(d.querySelector('[data-gallery="add"]').disabled, true);
    assert.equal(d.querySelector('[data-gallery="up"]').disabled, true);
    d.querySelector('[data-gallery="remove"]').click();
    assert.equal(d.querySelector('[data-gallery="add"]').disabled, false); w.close();
});
test('gallery validates media selection and renders unsafe filenames/URLs safely', () => {
    const w = galleryEditor(), d = w.document;
    w.selection = [{id: 51, type: 'application', url: 'https://example.test/file.pdf'}];
    d.querySelector('[data-gallery="add"]').click();
    assert.equal(d.querySelectorAll('.alify-gallery-item').length, 0);
    w.selection = [{id: 50, type: 'image', filename: '<script>bad()</script>', url: 'javascript:alert(1)'}];
    d.querySelector('[data-gallery="add"]').click();
    assert.equal(d.querySelectorAll('script, .alify-gallery-item img').length, 0);
    assert.equal(d.querySelector('.alify-gallery-title').textContent, '<script>bad()</script>'); w.close();
});
test('gallery works in newly added repeater rows with independent names and controls', () => {
    const w = repeaterEditor(), d = w.document;
    d.querySelector('template').content.querySelector('.alify-row').insertAdjacentHTML('beforeend', galleryMarkup('values[team][rows][__ROW__][photos]'));
    mockGalleryMedia(w); w.eval(fs.readFileSync('assets/gallery.js', 'utf8'));
    d.querySelector('[data-row-action="add"]').click(); d.querySelector('[data-row-action="add"]').click();
    const galleries = d.querySelectorAll('.alify-gallery');
    w.selection = [galleryImage(50)]; galleries[0].querySelector('[data-gallery="add"]').click();
    w.selection = [galleryImage(52)]; galleries[1].querySelector('[data-gallery="add"]').click();
    const a = galleries[0].querySelector('.alify-gallery-items input'), b = galleries[1].querySelector('.alify-gallery-items input');
    assert.equal(a.value, '50'); assert.equal(b.value, '52'); assert.notEqual(a.name, b.name);
    assert.match(a.name, /\[new_\d+_\d+\]\[photos\]\[ids\]\[\]/); assert.equal(a.name.includes('__ROW__'), false); w.close();
});
test('gallery builder supports top-level/subfield types without unsupported defaults', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Photos'); set(w, 'type', 'gallery');
    assert.equal(d.querySelector('[data-prop="default"]'), null);
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), true);
    set(w, 'type', 'repeater'); d.querySelector('[data-sub-action="add"]').click();
    const type = d.querySelector('[data-sub-prop="type"]'); type.value = 'gallery'; type.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[0].sub_fields[0].type, 'gallery'); assert.equal(d.querySelector('[data-sub-prop="default"]'), null); w.close();
});

test('gallery conditional targets disable submitted IDs while hidden and restore boundaries', () => {
    const w = galleryEditor(), d = w.document;
    const group = d.querySelector('.alify-values');
    group.insertAdjacentHTML('afterbegin', '<div class="alify-value" data-field-key="show" data-field-type="true_false"><input type="checkbox" value="1" checked></div>');
    const photos = d.querySelector('[data-field-key="photos"]');
    photos.dataset.condition = JSON.stringify({field: 'show', operator: 'equals', value: '1'});
    // A conditional source cannot drive another rule; test only the gallery target here.
    d.querySelector('[data-field-key="caption"]').dataset.condition = 'null';
    w.selection = [galleryImage(50)]; d.querySelector('[data-gallery="add"]').click();
    group.querySelector('[type="checkbox"]').click();
    assert.equal(photos.hidden, true); assert.equal(photos.querySelector('.alify-gallery-items input').disabled, true);
    assert.equal(photos.querySelector('.alify-gallery-complete').disabled, true);
    group.querySelector('[type="checkbox"]').click();
    assert.equal(photos.hidden, false); assert.equal(photos.querySelector('.alify-gallery-items input').disabled, false);
    assert.equal(photos.querySelector('[data-gallery="up"]').disabled, true); w.close();
});

test('editing a field clears stale inline feedback for inputs and media change events', () => {
    const w = conditionalEditor('text', 'equals', 'yes', '<input value="yes">'), d = w.document;
    const field = d.querySelector('[data-field-key="target"]');
    field.insertAdjacentHTML('beforeend', '<p class="alify-field-error">Invalid</p>');
    const input = field.querySelector('input'); input.setAttribute('aria-invalid', 'true');
    input.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(field.querySelector('.alify-field-error').hidden, true);
    assert.equal(input.hasAttribute('aria-invalid'), false);
    field.querySelector('.alify-field-error').hidden = false; input.setAttribute('aria-invalid', 'true');
    input.dispatchEvent(new w.Event('change', {bubbles: true}));
    assert.equal(field.querySelector('.alify-field-error').hidden, true);
    assert.equal(input.hasAttribute('aria-invalid'), false); w.close();
});

function editorBridge() {
    const dom = new JSDOM(`<div class="alify-values"><div class="alify-value"><input name="alify_values[7][title]" value="Hello"></div><input name="alify_values[7][tags][]" type="checkbox" checked value="a"><input name="alify_values[7][tags][]" type="checkbox" value="b"><input name="alify_values[7][hidden]" disabled value="ignore"><input name="alify_values[7][team][rows][0][name]" value="Member"><input name="alify_values[7][__group_complete]" value="1"></div>`, {runScripts: 'outside-only'});
    const w = dom.window;
    w.AlifyEditor = {path: '/wp/v2/posts', postId: '10'};
    w.wp = {apiFetch: {use(fn) { w.middleware = fn; }}};
    w.eval(fs.readFileSync('assets/editor.js', 'utf8'));
    return w;
}
test('editor bridge serializes enabled controls and nested arrays into matching core saves', async () => {
    const w = editorBridge(); let sent;
    await w.middleware({path: '/wp/v2/posts/10?context=edit', method: 'POST', data: {status: 'publish'}}, options => { sent = options; return Promise.resolve(); });
    assert.equal(sent.data.status, 'publish');
    assert.deepEqual(JSON.parse(JSON.stringify(sent.data.alify_fields)), {complete: '1', groups: {'7': {title: 'Hello', tags: ['a'], team: {rows: {'0': {name: 'Member'}}}, __group_complete: '1'}}});
    w.close();
});
test('editor bridge leaves reads, autosaves and unrelated posts unchanged', async () => {
    const w = editorBridge();
    for (const options of [{path: '/wp/v2/posts/10'}, {path: '/wp/v2/posts/11', method: 'POST'}, {path: '/wp/v2/posts/10/autosaves', method: 'POST'}]) {
        await w.middleware(options, received => { assert.equal(received, options); return Promise.resolve(); });
    }
    w.close();
});
test('editor bridge renders structured server errors as text and rethrows rejection', async () => {
    const w = editorBridge(); const error = {data: {alify_errors: {'7': {title: '<img src=x>Required'}}}};
    await assert.rejects(w.middleware({path: '/wp/v2/posts/10', method: 'PATCH'}, () => Promise.reject(error)), caught => caught === error);
    assert.equal(w.document.querySelector('.alify-field-error').textContent, '<img src=x>Required');
    assert.equal(w.document.querySelector('img'), null);
    assert.equal(w.document.querySelector('[name="alify_values[7][title]"]').getAttribute('aria-invalid'), 'true');
    w.close();
});


test('group builder provides basic subfields, excludes nested containers and copies independently', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Profile'); set(w, 'type', 'group');
    d.querySelector('[data-sub-action="add"]').click();
    const label = d.querySelector('[data-sub-prop="label"]'); label.value = 'Name'; label.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[0].sub_fields[0].name, 'name');
    assert.equal(d.querySelector('[data-sub-prop="type"] option[value="group"]'), null);
    assert.equal(d.querySelector('[data-sub-prop="type"] option[value="repeater"]'), null);
    assert.equal(d.querySelector('[data-prop="default"]'), null);
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), true);
    d.querySelector('[data-action="duplicate"]').click();
    const duplicateLabel = d.querySelectorAll('[data-sub-prop="label"]')[1]; duplicateLabel.value = 'Changed'; duplicateLabel.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[0].sub_fields[0].label, 'Name');
    w.close();
});


test('flexible builder creates named layouts and isolated basic subfields', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Content'); set(w, 'type', 'flexible');
    d.querySelector('[data-layout-action="add"]').click();
    const label = d.querySelector('[data-layout-prop="label"]'); label.value = 'Hero'; label.dispatchEvent(new w.Event('input', {bubbles: true}));
    d.querySelector('[data-sub-action="add"]').click();
    const sub = d.querySelector('[data-sub-prop="label"]'); sub.value = 'Title'; sub.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[0].layouts[0].name, 'hero');
    assert.equal(schema(w)[0].layouts[0].sub_fields[0].name, 'title');
    assert.equal(d.querySelector('[data-sub-prop="type"] option[value="flexible"]'), null);
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), true);
    d.querySelector('[data-action="duplicate"]').click();
    const copy = d.querySelectorAll('[data-sub-prop="label"]')[1]; copy.value = 'Changed'; copy.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[0].layouts[0].sub_fields[0].label, 'Title');
    w.close();
});
test('flexible editor adds, reorders and clears layout rows with unique submission names', () => {
    const dom = new JSDOM('<div class="alify-values"><div class="alify-value"><div class="alify-flexible" data-limit="2"><div class="alify-flex-rows"></div><template data-flex-template="hero"><div class="alify-flex-row"><span class="alify-flex-label"></span><input name="content[rows][__FLEX_ROW__][fields][title]" id="title-__FLEX_ROW__"><button data-flex-action="up">Up</button><button data-flex-action="down">Down</button><button data-flex-action="remove">Remove</button></div></template><select class="alify-flex-choice"><option value="hero">Hero</option></select><button data-flex-action="add">Add</button><span class="alify-flex-count"></span></div></div></div>', {runScripts: 'outside-only'});
    const w = dom.window, d = w.document; w.confirm = () => true;
    w.eval(fs.readFileSync('assets/flexible.js', 'utf8'));
    const add = d.querySelector('[data-flex-action="add"]'); add.click(); add.click();
    const inputs = d.querySelectorAll('.alify-flex-row input');
    assert.notEqual(inputs[0].name, inputs[1].name); assert.equal(add.disabled, true);
    inputs[0].value = 'First'; inputs[1].value = 'Second';
    d.querySelectorAll('[data-flex-action="up"]')[1].click();
    assert.equal(d.querySelector('.alify-flex-row input').value, 'Second');
    d.querySelector('[data-flex-action="remove"]').click(); assert.equal(add.disabled, false);
    d.querySelector('[data-flex-action="remove"]').click(); assert.equal(d.querySelectorAll('.alify-flex-row').length, 0);
    w.close();
});


test('compound builder adds/removes rules, selects ANY and deep-copies conditions', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Source');
    const sourceKey = schema(w)[0].key;
    d.getElementById('alify-add').click();
    const target = d.querySelectorAll('.alify-card')[1];
    target.querySelector('[data-prop="label"]').value = 'Target'; target.querySelector('[data-prop="label"]').dispatchEvent(new w.Event('input', {bubbles: true}));
    const enabled = target.querySelector('[data-condition-prop="enabled"]'); enabled.checked = true; enabled.dispatchEvent(new w.Event('input', {bubbles: true}));
    let cards = () => d.querySelectorAll('.alify-card');
    function chooseRule(index, prop, value) { const node = cards()[1].querySelectorAll('.alify-condition-rule')[index].querySelector(`[data-condition-prop="${prop}"]`); node.value = value; node.dispatchEvent(new w.Event('input', {bubbles: true})); }
    chooseRule(0, 'field', sourceKey);
    cards()[1].querySelector('[data-condition-action="add"]').click(); chooseRule(1, 'field', sourceKey); chooseRule(1, 'value', 'a');
    const relation = cards()[1].querySelector('[data-condition-relation]'); relation.value = 'any'; relation.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(schema(w)[1].condition.relation, 'any'); assert.equal(schema(w)[1].condition.rules.length, 2);
    cards()[1].querySelector('[data-action="duplicate"]').click();
    const copyValue = cards()[2].querySelector('[data-condition-prop="value"]'); copyValue.value = 'copy'; copyValue.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.notEqual(schema(w)[1].condition.rules[0].value, 'copy');
    cards()[1].querySelectorAll('[data-condition-action="remove"]')[1].click(); assert.equal(schema(w)[1].condition.rules.length, 1);
    w.close();
});
test('compound live conditions enforce ALL/ANY, disable hidden required values and fail closed on missing sources', () => {
    const rule = relation => JSON.stringify({relation, rules: [{field: 'field_show', operator: 'equals', value: '1'}, {field: 'field_kind', operator: 'equals', value: 'a'}]});
    const dom = new JSDOM(`<div class="alify-values"><div class="alify-value" data-field-key="field_show" data-field-type="true_false"><input type="checkbox" value="1"></div><div class="alify-value" data-field-key="field_kind" data-field-type="select"><select><option value="a">A</option><option value="b">B</option></select></div><div id="target" class="alify-value" data-field-key="field_detail" data-field-type="text"><input required value="keep"></div></div>`, {runScripts: 'outside-only'});
    const w = dom.window, d = w.document, target = d.getElementById('target'); target.dataset.condition = rule('all');
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    assert.equal(target.hidden, true); assert.equal(target.querySelector('input').disabled, true);
    const toggle = d.querySelector('[type="checkbox"]'); toggle.checked = true; toggle.dispatchEvent(new w.Event('change', {bubbles: true})); assert.equal(target.hidden, false);
    const choice = d.querySelector('select'); choice.value = 'b'; choice.dispatchEvent(new w.Event('change', {bubbles: true})); assert.equal(target.hidden, true);
    target.dataset.condition = rule('any'); choice.dispatchEvent(new w.Event('change', {bubbles: true})); assert.equal(target.hidden, false);
    const missing = JSON.parse(rule('any')); missing.rules[1].field = 'missing'; target.dataset.condition = JSON.stringify(missing); choice.dispatchEvent(new w.Event('change', {bubbles: true})); assert.equal(target.hidden, true);
    assert.equal(target.querySelector('input').value, 'keep'); w.close();
});


test('repeater builder validates configured bounds without changing legacy defaults', () => {
    const w = builder(), d = w.document;
    d.getElementById('alify-add').click(); set(w, 'label', 'Team'); set(w, 'type', 'repeater');
    assert.equal(d.querySelector('[data-prop="min_rows"]').value, '0'); assert.equal(d.querySelector('[data-prop="max_rows"]').value, '50');
    d.querySelector('[data-sub-action="add"]').click(); const label = d.querySelector('[data-sub-prop="label"]'); label.value = 'Name'; label.dispatchEvent(new w.Event('input', {bubbles: true}));
    set(w, 'min_rows', '2'); set(w, 'max_rows', '1');
    assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), false);
    set(w, 'max_rows', '3'); assert.equal(d.getElementById('post').dispatchEvent(new w.Event('submit', {cancelable: true})), true);
    assert.equal(schema(w)[0].min_rows, '2'); assert.equal(schema(w)[0].max_rows, '3');
    w.close();
});
test('repeater remove/add limits survive conditional visibility refresh and never discard existing rows', () => {
    const dom = new JSDOM('<div class="alify-values"><div class="alify-value"><div class="alify-repeater" data-min="1" data-limit="2"><div class="alify-rows"><div class="alify-row"><span class="alify-row-label"></span><input value="keep"><button data-row-action="up">Up</button><button data-row-action="down">Down</button><button data-row-action="remove">Remove</button></div></div><template class="alify-row-template"><div class="alify-row"><span class="alify-row-label"></span><input name="rows[__ROW__][name]"><button data-row-action="up">Up</button><button data-row-action="down">Down</button><button data-row-action="remove">Remove</button></div></template><button data-row-action="add">Add</button><span class="alify-row-count"></span></div></div></div>', {runScripts: 'outside-only'});
    const w = dom.window, d = w.document; w.confirm = () => true; w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    assert.equal(d.querySelector('[data-row-action="remove"]').disabled, true);
    d.querySelector('[data-row-action="add"]').click(); assert.equal(d.querySelector('[data-row-action="add"]').disabled, true);
    assert.equal(d.querySelector('[data-row-action="remove"]').disabled, false);
    d.querySelector('.alify-row input').dispatchEvent(new w.Event('change', {bubbles: true})); assert.equal(d.querySelector('[data-row-action="add"]').disabled, true);
    d.querySelectorAll('[data-row-action="remove"]')[1].click(); assert.equal(d.querySelector('[data-row-action="remove"]').disabled, true);
    assert.equal(d.querySelector('.alify-row input').value, 'keep');
    w.close();
});


test('REST feedback has accessible error references, clears on edit and relinks on repeated rejection', async () => {
    const w = editorBridge(), d = w.document;
    const input = d.querySelector('[name="alify_values[7][title]"]'); input.setAttribute('aria-describedby', 'existing-help');
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    const error = {data: {alify_errors: {'7': {title: 'Row 2 → Name: Required.'}}}};
    const reject = () => w.middleware({path: '/wp/v2/posts/10', method: 'POST'}, () => Promise.reject(error));
    await assert.rejects(reject(), caught => caught === error);
    const message = d.querySelector('.alify-field-error');
    assert.equal(input.getAttribute('aria-describedby'), 'existing-help ' + message.id);
    await assert.rejects(reject(), caught => caught === error);
    assert.equal(input.getAttribute('aria-describedby'), 'existing-help ' + message.id);
    input.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(message.hidden, true); assert.equal(input.getAttribute('aria-describedby'), 'existing-help'); assert.equal(input.hasAttribute('aria-invalid'), false);
    await assert.rejects(reject(), caught => caught === error);
    assert.equal(message.hidden, false); assert.equal(input.getAttribute('aria-describedby'), 'existing-help ' + message.id);
    w.close();
});

test('structured inline targeting uses row order and schema names, expands panel and clears without losing help', () => {
    const dom = new JSDOM(`<div class="postbox closed"><div class="inside"><div class="alify-value" data-field-type="repeater"><div class="alify-rows"><div class="alify-row"><div class="alify-sub-value" data-subfield="email"><input id="valid" value="ok"></div></div><div class="alify-row"><div class="alify-sub-value" data-subfield="email"><input id="invalid" aria-describedby="help"></div></div></div><p class="alify-field-error" id="problem" data-error-location='{"row":1,"subfield":"email"}'>Invalid email.</p></div></div></div>`, {runScripts: 'outside-only'});
    const w = dom.window, d = w.document;
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    const invalid = d.getElementById('invalid');
    assert.equal(d.activeElement, invalid);
    assert.equal(d.querySelector('.postbox').classList.contains('closed'), false);
    assert.equal(d.getElementById('valid').hasAttribute('aria-invalid'), false);
    assert.equal(invalid.getAttribute('aria-describedby'), 'help problem-inline');
    assert.equal(invalid.closest('.alify-sub-value').querySelector('.alify-subfield-error').textContent, 'Invalid email.');
    d.getElementById('valid').focus(); d.querySelector('.alify-error-jump').click(); assert.equal(d.activeElement, invalid);
    invalid.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(invalid.getAttribute('aria-describedby'), 'help');
    assert.equal(d.querySelector('.alify-subfield-error').hidden, true);
    assert.equal(d.querySelector('.alify-error-jump'), null);
    w.AlifyValidation.show(d.querySelector('.alify-value'), d.getElementById('problem'), {row: 0, subfield: 'email'});
    assert.equal(d.querySelectorAll('#problem-inline').length, 1);
    assert.equal(d.getElementById('valid').getAttribute('aria-describedby'), 'problem-inline');
    assert.equal(invalid.getAttribute('aria-describedby'), 'help');
    w.close();
});

test('REST structured location targets Flexible subfield and ignores a stale response after editing', async () => {
    const w = editorBridge(), d = w.document;
    const field = d.querySelector('.alify-value');
    field.dataset.fieldType = 'flexible';
    field.innerHTML = '<div class="alify-flex-rows"><div class="alify-flex-row"><div class="alify-sub-value" data-subfield="body"><input name="alify_values[7][blocks][rows][token][fields][body]" id="body"></div></div></div>';
    w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    const error = {data: {alify_errors: {'7': {blocks: 'Body: Required.'}}, alify_error_locations: {'7': {blocks: {row: 0, subfield: 'body'}}}}};
    await assert.rejects(w.middleware({path: '/wp/v2/posts/10', method: 'POST'}, () => Promise.reject(error)));
    assert.equal(d.activeElement.id, 'body');
    assert.equal(field.querySelector('.alify-subfield-error').textContent, 'Body: Required.');
    const input = d.getElementById('body'); input.value = 'Changed'; input.dispatchEvent(new w.Event('input', {bubbles: true}));
    let reject;
    const pending = w.middleware({path: '/wp/v2/posts/10', method: 'POST'}, () => new Promise((resolve, fail) => { reject = fail; }));
    input.value = 'Edited while saving'; input.dispatchEvent(new w.Event('input', {bubbles: true})); reject(error);
    await assert.rejects(pending);
    assert.equal(field.querySelector('.alify-field-error').hidden, true);
    assert.equal(input.hasAttribute('aria-invalid'), false);
    w.close();
});


test('color picker keeps empty distinct from black, synchronizes text, and explicitly clears', () => {
    const dom = new JSDOM('<div class="alify-value"><div class="alify-color"><input type="text" name="color" value=""><input type="color" data-color-picker value="#000000"><button type="button" data-color-apply>Apply</button><button type="button" data-color-clear>Clear</button></div></div>', {runScripts: 'outside-only'});
    const w = dom.window, d = w.document; w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    const text = d.querySelector('[name="color"]'), picker = d.querySelector('[data-color-picker]');
    assert.equal(text.value, ''); assert.equal(picker.hasAttribute('name'), false);
    d.querySelector('[data-color-apply]').click(); assert.equal(text.value, '#000000');
    d.querySelector('[data-color-clear]').click(); assert.equal(text.value, '');
    picker.value = '#2271b1'; picker.dispatchEvent(new w.Event('input', {bubbles: true})); assert.equal(text.value, '#2271b1');
    text.value = '#AABBCC'; text.dispatchEvent(new w.Event('input', {bubbles: true})); assert.equal(picker.value, '#aabbcc');
    text.value = 'bad'; text.dispatchEvent(new w.Event('input', {bubbles: true})); assert.equal(text.value, 'bad');
    d.querySelector('[data-color-clear]').click(); assert.equal(text.value, ''); assert.equal(d.activeElement, text);
    assert.equal(picker.value, '#000000'); w.close();
});

test('builder exposes Color and Time types with format-specific defaults guidance', () => {
    const w = builder(); w.document.getElementById('alify-add').click();
    set(w, 'label', 'Accent'); set(w, 'type', 'color'); set(w, 'default', '#AABBCC');
    assert.equal(schema(w)[0].type, 'color'); assert.match(w.document.body.textContent, /Six-digit hex/);
    set(w, 'type', 'time'); set(w, 'default', '09:30');
    assert.equal(schema(w)[0].type, 'time'); assert.equal(schema(w)[0].default, '09:30');
    assert.match(w.document.body.textContent, /24-hour HH:MM/); w.close();
});


test('Link clear resets all components but preserves completion and focuses URL', () => {
    const dom = new JSDOM('<div class="alify-value"><fieldset class="alify-link"><input type="url" value="https://example.com"><input type="text" value="Visit"><select><option value="">Same</option><option value="_blank" selected>New</option></select><input type="hidden" value="1"><button type="button" data-link-clear>Clear</button></fieldset></div>', {runScripts: 'outside-only'});
    const w = dom.window, d = w.document; w.eval(fs.readFileSync('assets/values.js', 'utf8'));
    d.querySelector('[data-link-clear]').click();
    assert.equal(d.querySelector('input[type="url"]').value, ''); assert.equal(d.querySelector('input[type="text"]').value, '');
    assert.equal(d.querySelector('select').value, ''); assert.equal(d.querySelector('input[type="hidden"]').value, '1');
    assert.equal(d.activeElement, d.querySelector('input[type="url"]')); w.close();
});

test('Link builder hides unsupported defaults and serializes its type', () => {
    const w = builder(); w.document.getElementById('alify-add').click(); set(w, 'label', 'Call to action'); set(w, 'type', 'link');
    assert.equal(schema(w)[0].type, 'link'); assert.equal(w.document.querySelector('[data-prop="default"]'), null); w.close();
});


test('rich editor synchronizes Visual before form saves but preserves active Text-tab edits', () => {
    const dom = new JSDOM('<form><div class="alify-rich"><button type="button" data-rich-enable>Enable</button><textarea id="rich" data-rich-editor>Original</textarea></div></form>', {runScripts: 'outside-only'});
    const w = dom.window, d = w.document; let settings, active = false, hidden = false;
    const textarea = d.getElementById('rich');
    const editor = {isHidden: () => hidden, save: () => { textarea.value = '<p>Visual</p>'; }, focus: () => {}};
    w.tinymce = {get: () => active ? editor : null};
    w.wp = {editor: {initialize(id, config) { assert.equal(id, 'rich'); settings = config; active = true; }}};
    w.eval(fs.readFileSync('assets/rich-text.js', 'utf8'));
    d.querySelector('button').click(); assert.equal(settings.mediaButtons, false); assert.equal(settings.quicktags, true);
    assert.equal(d.querySelector('button').hidden, true);
    d.querySelector('form').dispatchEvent(new w.Event('submit', {bubbles: true, cancelable: true})); assert.equal(textarea.value, '<p>Visual</p>');
    hidden = true; textarea.value = '<p>Text edit</p>'; w.AlifyRichText.sync(); assert.equal(textarea.value, '<p>Text edit</p>');
    w.close();
});

test('Gutenberg payload flushes rich editors before serialization', async () => {
    const w = editorBridge(), input = w.document.querySelector('[name="alify_values[7][title]"]'); let sent;
    w.AlifyRichText = {sync() { input.value = '<p>Latest Visual content</p>'; }};
    await w.middleware({path: '/wp/v2/posts/10', method: 'POST'}, options => { sent = options; return Promise.resolve(); });
    assert.equal(sent.data.alify_fields.groups['7'].title, '<p>Latest Visual content</p>'); w.close();
});

test('WYSIWYG builder uses multiline defaults and excludes rich subfields', () => {
    const w = builder(); w.document.getElementById('alify-add').click(); set(w, 'label', 'Copy'); set(w, 'type', 'wysiwyg');
    assert.equal(w.document.querySelector('[data-prop="default"]').tagName, 'TEXTAREA');
    set(w, 'type', 'repeater'); w.document.querySelector('[data-sub-action="add"]').click();
    assert.equal(w.document.querySelector('[data-sub-prop="type"] option[value="wysiwyg"]'), null); w.close();
});
