const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');
function builder() {
    const dom = new JSDOM('<form id="post"><input id="alify-schema" value="[]"><div id="alify-fields"></div><div id="alify-empty"></div><span id="alify-count"></span><button type="button" id="alify-add"></button></form>', {runScripts: 'outside-only'});
    const w = dom.window;
    w.AlifyCF = {types: {text: 'Text', image: 'Image', file: 'File', checkbox: 'Checkbox', select: 'Select', repeater: 'Repeater'}};
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
