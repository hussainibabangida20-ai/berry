(function () {
  'use strict';

  const schoolSelect = document.querySelector('#school_id');
  const departmentSelect = document.querySelector('[data-department-select]');
  if (schoolSelect && departmentSelect) {
    schoolSelect.addEventListener('change', async function () {
      departmentSelect.innerHTML = '<option value="">Loading departments…</option>';
      departmentSelect.disabled = true;
      if (!schoolSelect.value) {
        departmentSelect.innerHTML = '<option value="">Select a school first</option>';
        return;
      }

      const endpoint = new URL('index.php', window.location.href);
      endpoint.searchParams.set('page', 'departments');
      endpoint.searchParams.set('school_id', schoolSelect.value);
      try {
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Department list could not be loaded.');
        const departments = await response.json();
        departmentSelect.innerHTML = '<option value="">Select your department</option>';
        departments.forEach(function (department) {
          const option = document.createElement('option');
          option.value = department.id;
          option.textContent = department.name;
          departmentSelect.appendChild(option);
        });
        departmentSelect.disabled = departments.length === 0;
        if (departments.length === 0) departmentSelect.innerHTML = '<option value="">No departments available</option>';
      } catch (error) {
        departmentSelect.innerHTML = '<option value="">Could not load departments. Try again.</option>';
        console.error(error);
      }
    });
  }

  const courseSelect = document.querySelector('[data-course-select]');
  const generalCourse = document.querySelector('#general_course');
  if (courseSelect && generalCourse) {
    function syncCourseSelection() {
      courseSelect.disabled = generalCourse.checked || courseSelect.options.length < 2;
      if (generalCourse.checked) courseSelect.value = '';
    }
    generalCourse.addEventListener('change', syncCourseSelection);
    syncCourseSelection();
    if (courseSelect.options.length < 2) {
      courseSelect.insertAdjacentHTML('beforeend', '<option value="">No courses available yet</option>');
      courseSelect.disabled = true;
    }
  }

  const requestField = document.querySelector('#request');
  const characterCount = document.querySelector('[data-character-count]');
  if (requestField && characterCount) {
    const updateCount = function () {
      characterCount.textContent = String(requestField.value.length);
    };
    requestField.addEventListener('input', updateCount);
    updateCount();
  }
})();
