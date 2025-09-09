function load_strings(path)
	local environment = {};
	pcall(loadfile(path, 't', environment));
	return environment;
end

function load_localize(path)
	local strings = load_strings(path);
	local environment = {};
	for i = 1, math.huge do
		local name = debug.getupvalue(strings.Localize, i);
		if name == '_ENV' then
			debug.upvaluejoin(strings.Localize, i, function() return environment end, 1);
			break;
		end
	end
	pcall(strings.Localize);
	return environment;
end

function export_csv(path, data)
	local file = io.open(path, "w");
	io.output(file);
	for key, value in pairs(data) do
		io.write(string.format("\"%s\",\"%s\"\n", key, string.gsub(string.gsub(value, "\n", "\\n"), "\"", "\"\"")));
	end
	io.close(file);
end

function merge_tables(table_to, table_from)
	for key, value in pairs(table_from) do
		table_to[key] = value;
	end
	return table_to;
end

-- vanilla/Interface/FrameXML/Localization.lua
-- vanilla/Interface/GlueXML/GlueLocalization.lua

local GlobalStrings = load_strings('vanilla/Interface/FrameXML/GlobalStrings.lua');
local GlueStrings = load_strings('vanilla/Interface/GlueXML/GlueStrings.lua');

export_csv('csv/GlobalStrings.csv', GlobalStrings);
export_csv('csv/GlueStrings.csv', GlueStrings);
