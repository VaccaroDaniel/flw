moodle-block_sitenews
=====================

Display site news in full scale at the Dashboard.

Also available in the [Moodle plugins directory](https://moodle.org/plugins/view.php?plugin=block_sitenews).

Make it sticky
==============

After installation a block instance will be generated, which sticks to all 'My Home' pages automatically.
If you ever delete this instance, you may need to create a new one.
Although you may just reinstall the block you may also follow these steps to create a sticky instance:

1. Add block "Site news" to the startpage (**not** "My home page"!)
2. Open block configuration
3. Choose following options
  - In section "Where this block appears"
    * Choose "Display throughout the entire site"
    * Default region: "Content"
    * Default weight as you like
  - In section "On this page" 
    * Hide the block on the startpage by choosing "Visible: No"
4. Go to "My home" and open block configuration for "Site news"
5. Choose to display the block on all pages of type "My home page" and you're done

Release Notes
=============

If you have installed the plugin already, you may find it convinient to uninstall the block, upgrade it and reinstall the block again.
User settings won't be touched.

The new version has an update routine, which will either update the existing instance or genrate a new one.
So, if you weren't able to follow the instructions above you can also update your version in order to repair the block settings.
Please note, that this won't affect flawed user settings.  
