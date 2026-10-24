import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { validateTarget, selectedLocales, examplePoll, responseAnswers, IMAGE_STEMS } = require('../../scripts/screenshot-capture.js');
const languages = require('../../src/i18n/languages.json');

test('capture targets are restricted to high-port loopback origins', () => {
  assert.equal(validateTarget('http://127.0.0.1:18082'), 'http://127.0.0.1:18082');
  assert.equal(validateTarget('http://localhost:18083/'), 'http://localhost:18083');
  for (const value of ['https://example.com:18082', 'http://example.com:18082', 'http://localhost', 'http://localhost:80', 'http://localhost:18082/path', 'http://user:secret@localhost:18082', 'http://127.0.0.1.example.com:18082']) {
    assert.throws(() => validateTarget(value), /loopback/);
  }
});

test('all registered locales have complete localized ballot examples', async () => {
  assert.deepEqual(selectedLocales(), Object.keys(languages));
  assert.equal(IMAGE_STEMS.length, 11);
  assert.throws(() => selectedLocales('../../private'), /Unregistered/);
  for (const locale of Object.keys(languages)) {
    const poll = await examplePoll(locale);
    assert.equal(poll.locale, locale);
    assert.equal(poll.questions.length, 9);
    assert.equal(poll.questions[6].settings.preset, 'custom');
    assert.equal(poll.questions[6].settings.grades.length, 6);
    if (locale !== 'en') assert.notEqual(poll.title, (await examplePoll('en')).title);
    const questions = poll.questions.map((question, i) => ({ ...question, id: i + 1, options: question.options.map((option, j) => ({ ...option, id: 10 * i + j + 1 })) }));
    for (let voter = 0; voter < 4; voter++) {
      const answers = responseAnswers(questions, voter);
      assert.equal(Object.keys(answers).length, 9);
      assert.ok(questions[0].options.some(option => option.id === answers[1]));
      assert.ok(questions[6].settings.grades.includes(answers[7][questions[6].options[0].id]));
    }
  }
});
