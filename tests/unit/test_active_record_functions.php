<?php

class test_active_record_functions extends SimpletestUnitBase
{
    function testToCamelCase()
    {
        $this->assertEqual(to_camel_case('this_is_the_string'), 'ThisIsTheString', 'String should have the proper camel case');
        $this->assertEqual(to_camel_case('This_is_the_String'), 'ThisIsTheString', 'String should have the proper camel case');
        $this->assertEqual(to_camel_case('This_is_a_String'), 'ThisIsAString', 'String should have the proper camel case');
    }
    
    function testToUnderscore()
    {
        $this->assertEqual(to_underscore('ThisIsTheString'), 'this_is_the_string', 'String should have the proper camel case');
        $this->assertEqual(to_underscore('ThisIsAString'), 'this_is_a_string', 'String should have the proper camel case');
    }
    
    function testIsCamelCase()
    {
        $this->assertTrue(is_camel_case('ThisIsTheString'), 'Should validate as camel case');
        $this->assertTrue(is_camel_case('ThisIsAString'), 'Should validate as camel case');
        $this->assertTrue(is_camel_case('THISISaSTRING'), 'Should validate as camel case');
        
        $this->assertFalse(is_camel_case('THISIS aSTRING'), 'Should not validate as camel case');
        $this->assertFalse(is_camel_case('THIS-IS aSTRING'), 'Should not validate as camel case');
        $this->assertFalse(is_camel_case('This_is_a_String'), 'Should not validate as camel case');
    }
    
    function testIsUnderscore()
    {
        $this->assertTrue(is_underscore('this_is_the_string'), 'Should validate as underscore');
        $this->assertTrue(is_underscore('a_b_c_d'), 'Should validate as underscore');
        
        $this->assertFalse(is_underscore('this_fails_'), 'Should not validate as underscore');
        $this->assertFalse(is_underscore('_and_this'), 'Should not validate as underscore');
        $this->assertFalse(is_underscore('This_is_a_String'), 'Should not validate as underscore');
        $this->assertFalse(is_underscore('THISISaSTRING'), 'Should not validate as underscore');
    }
    
    function testToSingular()
    {
        $this->assertTrue(to_singular('ponies') == 'pony', 'Should singularize ies');
        $this->assertTrue(to_singular('potatoes') == 'potato', 'Should singularize oes');
        $this->assertTrue(to_singular('cards') == 'card', 'Should singularize s');
    }
    
    function testToPlural()
    {
        $this->assertTrue(to_plural('pony') == 'ponies', 'Should pluralize y');
        $this->assertTrue(to_plural('potato') == 'potatoes', 'Should pluralize o');
        $this->assertTrue(to_plural('card') == 'cards', 'Should singularize everything else');
    }
    
    function testTimer()
    {
        $start_time = start_timer();
        sleep(2);
        $end_time = time_elapsed();
        $this->assertTrue(abs($end_time - $start_time - 2) < 0.003, 'Timer should be accurate to .003');
    }
}

?>