#include <cstdint>
#include <windows.h>

#define dll(addr) reinterpret_cast<uintptr_t>(addr) - reinterpret_cast<uintptr_t>(GetModuleHandle(NULL))
#define exe(addr) reinterpret_cast<uintptr_t>(GetModuleHandle(NULL)) + reinterpret_cast<uintptr_t>(addr)

template <typename T>
void __fastcall write(uintptr_t addr, T data)
{
	auto addr_ptr = reinterpret_cast<T*>(exe(addr));
	auto size = sizeof(T);
	DWORD prot = PAGE_EXECUTE_READWRITE;
	DWORD prot_old;
	
	VirtualProtect(addr_ptr, size, prot, &prot_old);
	memcpy(addr_ptr, &data, size);
	VirtualProtect(addr_ptr, size, prot_old, &prot);
}

void __fastcall write_call(uintptr_t addr, uintptr_t call)
{
	write<uint8_t>(addr, 0xe8u);
	write<uintptr_t>(addr + 1u, call - addr - 5u);
}

void __fastcall write_jump(uintptr_t addr, uintptr_t jump)
{
	write<uint8_t>(addr, 0xe9u);
	write<uintptr_t>(addr + 1u, jump - addr - 5u);
}

uintptr_t __fastcall alloc(size_t size)
{
	return reinterpret_cast<uintptr_t>(VirtualAlloc(NULL, size, MEM_COMMIT | MEM_RESERVE, PAGE_READWRITE));
}

int32_t __fastcall free(uintptr_t addr)
{
	return VirtualFree((LPVOID)addr, 0, MEM_RELEASE);
}

void adjust_log(uint32_t size)
{
	uint32_t stack_size = size * 2u + 8u;
	uint32_t logdescription_stack = -(size + 8u);
	uint32_t logobjectives_stack = -(size * 2u + 8u);
	
	write<uint32_t>(0x000dff38u, stack_size);
	write<uint32_t>(0x000dff76u, size);
	write<uint32_t>(0x000dff82u, logdescription_stack);
	write<uint32_t>(0x000dff9eu, size);
	write<uint32_t>(0x000dffaau, logobjectives_stack);
	write<uint32_t>(0x000dffb5u, logdescription_stack);
	write<uint32_t>(0x000dffc2u, logobjectives_stack);
}

void adjust_available(uint8_t size, uintptr_t &available_ptr, uintptr_t &active_ptr)
{
	uint8_t str_size = size - 12u;
	
	available_ptr = alloc(size * 32u);
	uintptr_t available_unk2 = available_ptr + 4u;
	uintptr_t available_unk3 = available_unk2 + 4u;
	uintptr_t available_unk4 = available_unk3 + str_size;
	
	active_ptr = alloc(size * 32u);
	uintptr_t active_unk2 = active_ptr + 4u;
	uintptr_t active_unk3 = active_unk2 + 4u;
	uintptr_t active_unk4 = active_unk3 + str_size;
	
	write<uint8_t>(0x001dbbf5u, str_size);
	write<uint8_t>(0x001dbc45u, str_size);
	
	write<uintptr_t>(0x00100b23u, available_ptr);
	write<uintptr_t>(0x00100b29u, available_unk2);
	write<uintptr_t>(0x00100b2fu, available_unk3);
	write<uintptr_t>(0x00100b35u, available_unk4);
	write<uintptr_t>(0x00100b3bu, active_ptr);
	write<uintptr_t>(0x00100b41u, active_unk2);
	write<uintptr_t>(0x00100b47u, active_unk3);
	write<uintptr_t>(0x00100b4du, active_unk4);
	write<uint8_t>(0x00100b53u, size);
	
	write<uintptr_t>(0x00100c48u, available_ptr);
	write<uintptr_t>(0x00100c4eu, available_unk2);
	write<uintptr_t>(0x00100c54u, available_unk3);
	write<uintptr_t>(0x00100c5au, available_unk4);
	write<uintptr_t>(0x00100c60u, active_ptr);
	write<uintptr_t>(0x00100c66u, active_unk2);
	write<uintptr_t>(0x00100c6cu, active_unk3);
	write<uintptr_t>(0x00100c72u, active_unk4);
	write<uint8_t>(0x00100c78u, size);
	
	write<uint8_t>(0x00100e4au, size);
	write<uintptr_t>(0x00100e4fu, available_ptr);
	write<uintptr_t>(0x00100e58u, available_unk2);
	write<uint8_t>(0x00100e5fu, str_size);
	write<uintptr_t>(0x00100e63u, available_unk3);
	write<uint8_t>(0x00100e79u, size);
	write<uintptr_t>(0x00100e7du, available_unk4);
	
	write<uint8_t>(0x00100e9au, size);
	write<uintptr_t>(0x00100e9fu, active_ptr);
	write<uintptr_t>(0x00100ea8u, active_unk2);
	write<uint8_t>(0x00100eafu, str_size);
	write<uintptr_t>(0x00100eb3u, active_unk3);
	write<uint8_t>(0x00100ec6u, size);
	write<uintptr_t>(0x00100ecau, active_unk4);
	
	write<uint8_t>(0x001012dau, size);
	write<uintptr_t>(0x001012ddu, available_unk4);
	write<uintptr_t>(0x001012e7u, available_ptr);
	write<uintptr_t>(0x00101304u, available_ptr);
	write<uint8_t>(0x00101aefu, size);
	write<uintptr_t>(0x00101af1u, available_unk3);
	write<uint8_t>(0x00101bd3u, size);
	write<uintptr_t>(0x00101bd6u, available_unk2);
	
	write<uint8_t>(0x00101356u, size);
	write<uintptr_t>(0x00101359u, active_ptr);
	write<uint8_t>(0x00101b5fu, size);
	write<uintptr_t>(0x00101b61u, active_unk3);
	write<uint8_t>(0x00101c53u, size);
	write<uintptr_t>(0x00101c56u, active_unk2);
}

void adjust_title(uint8_t size, uintptr_t &title_ptr)
{
	title_ptr = alloc(size);
	
	write<uintptr_t>(0x00100b67u, title_ptr);
	write<uintptr_t>(0x00100c90u, title_ptr);
	write<uint8_t>(0x00101002u, size);
	write<uintptr_t>(0x0010100eu, title_ptr);
	write<uint8_t>(0x001010ecu, size);
	write<uintptr_t>(0x001010f8u, title_ptr);
	write<uintptr_t>(0x00101a21u, title_ptr);
}

void adjust_greeting(uint32_t greeting_size, uint32_t size, uintptr_t &greeting_ptr, uintptr_t &quest_ptr, uintptr_t &progress_ptr, uintptr_t &reward_ptr)
{
	uint32_t stack_size = size + 16u;
	uint32_t local_stack = -(size + 16u);

	greeting_ptr = alloc(greeting_size);
	quest_ptr = alloc(size);
	progress_ptr = alloc(size);
	reward_ptr = alloc(size);
	
	write<uint32_t>(0x00100be8u, stack_size);
	write<uint32_t>(0x00100cbcu, size);
	write<uint32_t>(0x00100cc2u, local_stack);
	write<uint32_t>(0x00100cd0u, local_stack);
	write<uint32_t>(0x00100cd9u, size);
	write<uint32_t>(0x00100ce4u, local_stack);
	write<uint32_t>(0x00100d04u, local_stack);
	write<uint32_t>(0x00100d17u, local_stack);
	write<uint32_t>(0x00100d2au, local_stack);
	write<uint32_t>(0x00100d4fu, local_stack);

	write<uintptr_t>(0x00100b62u, greeting_ptr);
	write<uintptr_t>(0x00100c8au, greeting_ptr);
	write<uint32_t>(0x00100cfeu, greeting_size);
	write<uintptr_t>(0x00100d0au, greeting_ptr);
	write<uintptr_t>(0x00101a31u, greeting_ptr);
	
	write<uintptr_t>(0x00100b6cu, quest_ptr);
	write<uintptr_t>(0x00100c96u, quest_ptr);
	write<uint32_t>(0x00100d11u, size);
	write<uintptr_t>(0x00100d1du, quest_ptr);
	write<uintptr_t>(0x00101a41u, quest_ptr);
	
	write<uintptr_t>(0x00100b76u, progress_ptr);
	write<uintptr_t>(0x00100ca2u, progress_ptr);
	write<uint32_t>(0x00100d24u, size);
	write<uintptr_t>(0x00100d30u, progress_ptr);
	write<uintptr_t>(0x00101a61u, progress_ptr);
	
	write<uintptr_t>(0x00100b7bu, reward_ptr);
	write<uintptr_t>(0x00100ca8u, reward_ptr);
	write<uint32_t>(0x00100d49u, size);
	write<uintptr_t>(0x00100d55u, reward_ptr);
	write<uintptr_t>(0x00101a71u, reward_ptr);
}

void adjust_objective(uint32_t size, uintptr_t &objective_ptr)
{
	uint32_t stack_size = size + 12u;
	uint32_t local_stack = -(size + 8u);
	
	objective_ptr = alloc(size);
	
	write<uint32_t>(0x00100dd8u, stack_size);
	write<uint32_t>(0x00100df8u, size);
	write<uint32_t>(0x00100dfeu, local_stack);
	write<uint32_t>(0x00100e0au, size);
	write<uint32_t>(0x00100e10u, local_stack);
	
	write<uintptr_t>(0x00100b71u, objective_ptr);
	write<uintptr_t>(0x00100c9cu, objective_ptr);
	write<uintptr_t>(0x00100e16u, objective_ptr);
	write<uintptr_t>(0x00100e2fu, objective_ptr);
	write<uintptr_t>(0x00101a51u, objective_ptr);
}

bool verify_locale(char* locale)
{
	auto default_locales = reinterpret_cast<char**>(exe(0x004558a4u));
	char english_locales[][5] = {"enGB", "enCN", "enTW"};
	
	for(auto l = 0; l < 8; l++)
	{
		for(auto i = 0; i < 5; i++)
			if(locale[i] != default_locales[l][i])
				goto not_equal_default;
		
		return false;
		not_equal_default: continue;
	}
	
	for(auto l = 0; l < 3; l++)
	{
		for(auto i = 0; i < 5; i++)
			if(locale[i] != english_locales[l][i])
				goto not_equal_english;
		
		return false;
		not_equal_english: continue;
	}
	
	return true;
}

void write_xxyy(char* locale)
{
	for(auto i = 0; i < 5; i++)
	{
		write<uint8_t>(0x004558e4u + i, locale[i]);
		write<uint8_t>(0x004558e3u - i, locale[i]);
	}
}

int32_t hook_locale(char* locale, uint8_t length, char* format, char* language, char* country)
{
	using func_t = decltype(&hook_locale);
	uintptr_t func_addr = exe(0x0024a7f0u);
	auto sprintf = reinterpret_cast<func_t>(func_addr);
	auto repl = sprintf(locale, length, format, language, country);
	
	if(verify_locale(locale))
		write_xxyy(locale);
	
	write_call(0x00002513u, func_addr);
	return repl;
}

void auto_locale()
{
	write_call(0x00002513u, dll(&hook_locale));
}

void signature_bypass()
{
	write_jump(0x0006a936u, 0x0006a99eu);
	write_jump(0x0008ff78u, 0x0008ff95u);
}

void profanity_bypass()
{
	write_jump(0x002c9756u, 0x002c9a41u);
}

BOOL WINAPI DllMain(HINSTANCE hinstDLL, DWORD fdwReason, LPVOID lpReserved)
{
	uintptr_t AvailableQuests;
	uintptr_t ActiveQuests;
	uintptr_t TitleText;
	uintptr_t GreetingText;
	uintptr_t QuestText;
	uintptr_t ProgressText;
	uintptr_t RewardText;
	uintptr_t ObjectiveText;
	
	switch(fdwReason)
	{
		case DLL_PROCESS_ATTACH:
			adjust_log(2048u);
			adjust_available(124u, AvailableQuests, ActiveQuests);
			adjust_title(112u, TitleText);
			adjust_greeting(1024u, 2048u, GreetingText, QuestText, ProgressText, RewardText);
			adjust_objective(2048u, ObjectiveText);
			signature_bypass();
			profanity_bypass();
			auto_locale();
			break;
		case DLL_PROCESS_DETACH:
			free(AvailableQuests);
			free(ActiveQuests);
			free(TitleText);
			free(GreetingText);
			free(QuestText);
			free(ProgressText);
			free(RewardText);
			free(ObjectiveText);
			break;
	}
	
	return TRUE;
}
