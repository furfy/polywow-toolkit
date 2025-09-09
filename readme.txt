stuff i used to make russian pack in WoW 1.12
https://furfy.github.io/wow

can be used for backporting 1.14 localizations to the old client
has a lot of *unexperienced* moments, be warned

all you need are those things:
 PHP - with mysqli enabled
 Lua - used for the export_string.lua script
 db2 library - (included as a submodule) for reading db2 files
 classic/dbfilesclient folder - unpacked modern db2 client files
 vanilla/Interface folder - FrameXML and GlueXML code required for exporting strings
 csv folder - exported dbc files using the WDBX Editor (not good)
 csv folder - (again) with lua exported variables using the export_strings.lua script
 custom folder - (included with the russian stuff) pretty much a patch set
 C++ x32 - for the polywow.dll plugin if you want to make some changes

it is also supports translating the server database:
 MariaDB - the MySQL server
 classicmangos db - (as an example) populated world database

what the polywow.dll does?
it enforces any language set in the Wow.ini file to be valid and use the eight
column in the dbc tables (enTW), increases some string buffers, allows to use any
language in names and disables all protection related to the FrameXML and GlueXML files
