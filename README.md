Verbose CSV Importer
==========================

1. Allows for dry run importing.
1. Allows for very verbose feedback on CSV imports.
1. Imports grades regardless of small errors within the CSV.
1. Allows for copy/paste from Excel like programs.

TODO
-----------

1. Convert hard coded language to strings.
1. Add help icons and help to each technical term.
1. Create admin settings page instead of assumptions where this makes sense.
 1. Give admins the ability to hide and force options their school may find confusing.
 1. Give admins the ability to set the defualt screen (file vs paste).
 1. Make all options configurable for the admin. The current defaults are good startign points.
  1. Log changed settings on import so we can report on them later and determine what more reasonable defaults should be.

Revisions
=========

Summary
-------
2014-09-02
  Fixed bug in null override option
  Fix paste error with leading blank lines
  Require feedback or scores mapping

Currently there is a bug or feature in the default import code. The import was designed to allow selected scores and feedback columns to be imported. And both should be present or these side effects happen: 
 1. if a score is imported, any existing feedback will be reset or set to mapped column
 1. if feedback is mapped and no grade item mapping exists, any existing score is reset

Not understanding the intent, I have have duplicated the grade/import/lib.php function grade_import_commit as grade_verbose_import_commit and passing nullignore as a parameter. 
Generally, we would like the finalgrade and feedback to operate independently.
Our defaults are to ignore nulls, but feel we need to allow the option to treat them as zeroes and let them overlay existing scores.
 For an existing grade, change includes:
  
                     if (!$importfeedback) {
                         $grade->feedback = false; // ignore it
                     }
+                    if ($nullignore and $grade->finalgrade === NULL) { $grade->finalgrade = false; }
+                    if ($grade->feedback === NULL) { $grade->feedback = false; }
+
                     if (!$gradeitem->update_final_grade($grade->userid, $grade->finalgrade, 'import', $grade->feedback)) {

