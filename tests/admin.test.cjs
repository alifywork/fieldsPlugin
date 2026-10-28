const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');
function builder() {
    const dom = new JSDOM('<form id="post"><input id="alify-schema" value="[]"><div id="alify-fields"></div><div id="alify-empty"></div><span id="alify-count"></span><button type="button" id="alify-add"></button></form>', {runScripts: 'outside-only'});
    const w = dom.window;
    w.AlifyCF = {types: {text: 'Text', image: 'Image', file: 'File', checkbox: 'Checkbox', select: 'Select'}};
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
