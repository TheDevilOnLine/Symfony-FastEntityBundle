# DotCommerce's FastEntityBundle
Fast entity bundle for Symfony 2, used to generate dropdowns from entities faster than the default 'entity' formtype. To do this a new form type has to be generated for the entity used in the dropdown

## Usage
You need to use the command line tool from Symfony (app/console) to generate the new form types. The syntax is simple:
	$ app/console dotcommerce:generate:fastentity <BundleName>[:<EntityName>] [<FieldName>]
	
- BundleName is the name of you Bundle (i.e. MyBundle)
- EntityName (optional) is the name of your entity (i.e. MyEntity), if not defined it will generate form types for all the entities in the bundle
- FieldName (optional) the name of the field which is displayed in the dropdown, if not defined it will try to use the field 'name'

So if I want to generate a fast form type, for my entity Customer which is in my StoreBundle and I want to display the lastname of the customer in the dropdown, I need to use command:
	$ app/console dotcommerce:generate:fastentity StoreBundle:Customer lastname
	
To use the newly generated form type you have to specify it manualy in a Form. The name for your new formtype is 'fast<EntityName | lower>', i.e. for the above generated Customer entity, the formtype is called 'fastcustomer'.

## Last updates
**2012-12-07**
- First public version